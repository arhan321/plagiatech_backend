<?php

// namespace App\Services;

// use App\Models\Document;
// use Spatie\PdfToText\Pdf;
// use PhpOffice\PhpWord\IOFactory;
// use OpenAI\Laravel\Facades\OpenAI;
// use Illuminate\Support\Facades\Auth;

// class PlagiarismService
// {
//     public function checkPlagiarism(Document $document)
//     {
//         $text = $this->extractText($document);
//         $text = mb_convert_encoding($text, 'UTF-8', 'auto');
//         $embedding = OpenAI::embeddings()->create([
//             'model' => 'text-embedding-3-small',
//             'input' => $text,
//         ]);

//         $vectorNew = $embedding->embeddings[0]->embedding;

//         $results = [];
//         $documents = Document::where('id', '!=', $document->id)->get();

//         foreach ($documents as $doc) {
//             $textDb = $this->extractText($doc);
//             $textDb = mb_convert_encoding($textDb, 'UTF-8', 'auto');

//             $embeddingDb = OpenAI::embeddings()->create([
//                 'model' => 'text-embedding-3-small',
//                 'input' => $textDb,
//             ]);

//             $vectorDb = $embeddingDb->embeddings[0]->embedding;

//             $similarity = $this->cosineSimilarity($vectorNew, $vectorDb);

//             $results[] = [
//                 'doc_id'     => $doc->id,
//                 'title'      => $doc->title,
//                 'similarity' => round($similarity * 100, 2),
//             ];
//         }

//         return $results;
//     }

//     private function cosineSimilarity(array $vecA, array $vecB): float
//     {
//         $dotProduct = 0.0;
//         $normA = 0.0;
//         $normB = 0.0;

//         for ($i = 0; $i < count($vecA); $i++) {
//             $dotProduct += $vecA[$i] * $vecB[$i];
//             $normA += $vecA[$i] ** 2;
//             $normB += $vecB[$i] ** 2;
//         }

//         return $dotProduct / (sqrt($normA) * sqrt($normB));
//     }

//     private function extractText(Document $document): string
//     {
//         $path = storage_path("app/public/" . $document->filename);
//         $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

//         $text = '';

//         if ($ext === 'pdf') {
//             $text = Pdf::getText($path);
//         } elseif (in_array($ext, ['doc', 'docx'])) {
//             $phpWord = IOFactory::load($path);
//             foreach ($phpWord->getSections() as $section) {
//                 foreach ($section->getElements() as $element) {
//                     if (method_exists($element, 'getText')) {
//                         $text .= $element->getText() . "\n";
//                     }
//                 }
//             }
//         } elseif ($ext === 'txt') {
//             $text = file_get_contents($path);
//         }

//         // fallback kalau kosong
//         if (empty(trim($text))) {
//             $text = "Dokumen tidak bisa diekstrak.";
//         }

//         return $text;
//     }
// }


namespace App\Services;

use GuzzleHttp\Client;
use App\Models\Document;

class PlagiarismService
{
    public function checkPlagiarism(Document $document)
    {
        // 🔹 Ambil teks dari dokumen user
        $textNew = $this->normalize($this->extractText($document));

        // 🔹 Cari jurnal dari Semantic Scholar
        $papers = $this->searchPapers($document->title);

        $results = [];
        foreach ($papers as $paper) {
            $abstract = $this->normalize($paper['abstract'] ?? '');
            if (!$abstract) continue;

            try {
                // 🔹 Pakai HuggingFace embeddings
                $vectorNew = $this->getEmbeddingFromHF($textNew);
                $vectorDb  = $this->getEmbeddingFromHF($abstract);

                $similarity = $this->cosineSimilarity($vectorNew, $vectorDb) * 100;
            } catch (\Exception $e) {
                // 🔹 Kalau gagal, fallback ke built-in
                $similarity = $this->calculateSimilarity($textNew, $abstract);
            }

            $results[] = [
                'title'      => $paper['title'] ?? 'Unknown Title',
                'url'        => $paper['url'] ?? null,
                'similarity' => round($similarity, 2),
            ];
        }

        return $results;
    }

    /**
     * 🔹 Ambil isi file (sementara raw, bisa ditingkatkan pakai parser)
     */
    private function extractText(Document $document): string
    {
        $path = storage_path("app/public/" . $document->filename);
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $text = '';
        if ($ext === 'txt') {
            $text = file_get_contents($path);
        } elseif (in_array($ext, ['doc', 'docx', 'pdf'])) {
            // sementara: raw (disarankan tambah parser Word/PDF)
            $text = @file_get_contents($path) ?: '';
        }

        return mb_convert_encoding($text, 'UTF-8', 'auto');
    }

    /**
     * 🔹 Normalisasi teks biar lebih konsisten
     */
    private function normalize(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/\s+/', ' ', $text); // rapikan spasi
        $text = trim($text);
        return $text;
    }

    /**
     * 🔹 Query ke Semantic Scholar API
     */
    private function searchPapers(string $query): array
    {
        $client = new Client();
        $url = "https://api.semanticscholar.org/graph/v1/paper/search";

        $response = $client->get($url, [
            'query' => [
                'query'  => $query,
                'limit'  => 5,
                'fields' => 'title,url,abstract',
            ],
            'timeout' => 20,
        ]);

        $result = json_decode($response->getBody(), true);
        return $result['data'] ?? [];
    }

    /**
     * 🔹 HuggingFace Embedding API
     */
    private function getEmbeddingFromHF(string $text): array
    {
        $client = new Client();
        $url = "https://api-inference.huggingface.co/pipeline/feature-extraction/sentence-transformers/all-MiniLM-L6-v2";

        $response = $client->post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . env('HUGGINGFACE_API_KEY'),
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode(["inputs" => $text]),
            'timeout' => 30,
        ]);

        $result = json_decode($response->getBody(), true);

        if (!is_array($result)) {
            throw new \Exception("Invalid response from HuggingFace");
        }

        return $result[0];
    }

    /**
     * 🔹 Hitung Cosine Similarity antara 2 vector
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $len = min(count($a), count($b));
        for ($i = 0; $i < $len; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] ** 2;
            $normB += $b[$i] ** 2;
        }

        return ($normA && $normB) ? $dotProduct / (sqrt($normA) * sqrt($normB)) : 0.0;
    }

    /**
     * 🔹 Fallback: pakai fungsi PHP built-in
     */
    private function calculateSimilarity(string $a, string $b): float
    {
        if (!$a || !$b) return 0.0;
        similar_text($a, $b, $percent);
        return $percent;
    }
}