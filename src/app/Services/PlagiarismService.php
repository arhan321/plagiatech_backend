<?php

namespace App\Services;

use App\Models\Document;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpWord\IOFactory;
use Spatie\PdfToText\Pdf;
use App\Services\EmbedService; // <— ADD: untuk embeddings (Ollama)

class PlagiarismService
{
    // Warna untuk highlight (akan diputar jika > palette)
    private array $palette = [
        '#ffd54f', // kuning
        '#ff8a65', // oranye
        '#4fc3f7', // biru muda
        '#a5d6a7', // hijau
        '#ce93d8', // ungu
        '#90a4ae', // abu
        '#f48fb1', // pink
        '#ffccbc', // peach
    ];

    /** cache embedding sederhana (in-process) */
    private array $embedCache = [];

    public function __construct(private ?EmbedService $embed = null) {} // <— ADD: DI optional

    public function checkPlagiarism(Document $document): array
    {
        $raw = $this->extractText($document);
        if ($raw === '') return [];

        // ===== Tokenisasi raw + offset karakter =====
        $docTok    = $this->docTokensWithOffsets($raw);
        $tokensNew = $docTok['tokens'];
        $offsets   = $docTok['offsets'];
        $N         = count($tokensNew);
        if ($N < 30) return [];

        $textNew = implode(' ', $tokensNew); // untuk TF-IDF dsb

        // ==== PREP: Doc embedding (opsional) ====
        $vecDoc = [];
        $cosMode = 'tfidf';
        if ($this->embed) {
            try {
                $vecDoc = $this->embedText($textNew);
                if ($vecDoc) $cosMode = 'embed';
            } catch (\Throwable $e) {
                report($e);
                $vecDoc = [];
                $cosMode = 'tfidf';
            }
        }

        // ====== BUAT BEBERAPA QUERY ======
        $queries = [];
        if ($document->title) $queries[] = trim($document->title);

        $kw = $this->topKeywords($tokensNew, 8);
        if ($kw) {
            $q1 = implode(' ', array_slice($kw, 0, min(3, count($kw))));
            if ($q1) $queries[] = $q1;
            if (count($kw) >= 5) {
                $q2 = $kw[2] . ' ' . $kw[3] . ' ' . $kw[4];
                $queries[] = trim($q2);
            }
        }
        if (!$queries) $queries[] = mb_substr($textNew, 0, 80);
        $queries = array_values(array_unique(array_filter($queries)));

        // ====== KUMPULKAN KANDIDAT (multi-query + paging) ======
        $candidateMap = [];
        foreach ($queries as $q) {
            foreach ($this->searchArxivPaged($q, 60, 30) as $row) {
                $sig = md5(($row['url'] ?? '') . '|' . ($row['title'] ?? ''));
                $candidateMap[$sig] = $row;
            }
        }
        if (empty($candidateMap)) {
            foreach ($this->searchArxiv($queries[0] ?? '', 16) as $row) {
                $sig = md5(($row['url'] ?? '') . '|' . ($row['title'] ?? ''));
                $candidateMap[$sig] = $row;
            }
        }
        $candidates = array_values($candidateMap);
        if (!$candidates) return [];

        // ====== Matching n-gram & kumpulkan SEMUA sumber yang match ======
        $n = 3;
        $docShinglePos = $this->shinglePositions($tokensNew, $n);
        $coverageOwner = array_fill(0, $N, -1);

        $allSources = [];
        foreach ($candidates as $paper) {
            $abstract = $this->normalize($paper['summary'] ?? '');
            if ($abstract === '') continue;

            $tokensAbs = $this->tokenize($abstract);
            if (count($tokensAbs) < $n) continue;

            $matches = $this->matchRanges($docShinglePos, $tokensAbs, $n);
            if (empty($matches)) continue;

            // Metrik pendamping
            $jaccard = $this->jaccard(
                $this->makeShingles($tokensNew, $n),
                $this->makeShingles($tokensAbs, $n)
            );

            // Cosine: gunakan EMBEDDINGS kalau ada; fallback TF-IDF kalau tidak
            $cosinePct = 0.0;
            $cosineType = 'tfidf';
            if ($vecDoc) {
                try {
                    $vecAbs = $this->embedText($abstract);
                    if ($vecAbs) {
                        $cosinePct = $this->embed->cosine($vecDoc, $vecAbs) * 100.0;
                        $cosineType = 'embed';
                    } else {
                        $cosinePct = $this->tfidfCosine($textNew, $abstract) * 100.0;
                        $cosineType = 'tfidf';
                    }
                } catch (\Throwable $e) {
                    report($e);
                    $cosinePct = $this->tfidfCosine($textNew, $abstract) * 100.0;
                    $cosineType = 'tfidf';
                }
            } else {
                $cosinePct = $this->tfidfCosine($textNew, $abstract) * 100.0;
                $cosineType = 'tfidf';
            }

            $allSources[] = [
                'title'       => $paper['title'],
                'url'         => $paper['url'],
                'pdf_url'     => $paper['pdf_url'],
                'jaccard'     => is_float($jaccard) ? round($jaccard * 100, 2) : 0.0,
                'cosine'      => round($cosinePct, 2),   // <- tetap nama 'cosine' (kompatibel view)
                'cosine_type' => $cosineType,            // <- info tambahan (optional dipakai di view)
                'ranges'      => $matches,               // [startTok, endTok]
            ];
        }
        if (empty($allSources)) return [];

        // Luas cakupan per sumber & urutkan
        foreach ($allSources as &$s) $s['covered_tokens'] = $this->countTokensCovered($s['ranges']);
        unset($s);
        usort($allSources, fn($a, $b) => $b['covered_tokens'] <=> $a['covered_tokens']);

        // ====== COVERAGE OWNER dari SEMUA SUMBER (union semua) ======
        foreach ($allSources as $i => $s) {
            foreach ($s['ranges'] as [$a, $b]) {
                for ($p = $a; $p <= $b && $p < $N; $p++) {
                    if ($coverageOwner[$p] === -1) $coverageOwner[$p] = $i;
                }
            }
        }

        // Overall union coverage
        $coveredTotal = 0;
        foreach ($coverageOwner as $own) if ($own !== -1) $coveredTotal++;
        $overall = $N > 0 ? round(100.0 * $coveredTotal / $N, 2) : 0.0;

        // Hitung kontribusi “menang” per sumber
        foreach ($allSources as $i => &$s) {
            $win = 0;
            for ($t = 0; $t < $N; $t++) if ($coverageOwner[$t] === $i) $win++;
            $s['win_tokens']  = $win;
            $s['contrib_pct'] = $N > 0 ? round(100.0 * $win / $N, 2) : 0.0;
        }
        unset($s);

        // ====== Pilih Top-K untuk ditampilkan berwarna; sisanya = Others ======
        $TOP_K   = 10;
        $display = array_slice($allSources, 0, min($TOP_K, count($allSources)));
        foreach ($display as $i => &$s) {
            $s['color'] = $this->palette[$i % count($this->palette)] ?? '#fff59d';
            // siapkan snippet berbasis CHAR
            $charRanges = [];
            foreach ($s['ranges'] as [$a, $b]) {
                $charRanges[] = [$offsets[$a][0], $offsets[$b][1]];
            }
            $s['char_ranges'] = $this->mergeIntervals($charRanges);
            $s['snippets']    = $this->makeSnippetsFromChar($raw, $s['char_ranges'], 120, 2);
        }
        unset($s);

        $othersCount = max(0, count($allSources) - count($display));
        $othersWin   = 0;
        for ($t = 0; $t < $N; $t++) {
            $own = $coverageOwner[$t];
            if ($own !== -1 && $own >= count($display)) $othersWin++;
        }
        $othersPct = $N > 0 ? round(100.0 * $othersWin / $N, 2) : 0.0;

        // ====== Build segmen CHAR & render highlight (warna hanya Top-K) ======
        $charSegments = $this->buildCharSegments($raw, $offsets, $coverageOwner);
        $coloredHtml  = $this->renderColoredHtmlFromRaw($raw, $charSegments, $display);

        return [
            'overall'        => $overall,
            'sources'        => $display,             // Top-K berwarna
            'sources_total'  => count($allSources),   // semua match
            'others_summary' => ['count' => $othersCount, 'contrib_pct' => $othersPct],
            'colored_html'   => $coloredHtml,
            'token_count'    => $N,
            'covered_count'  => $coveredTotal,
        ];
    }

    /* ================= Matching & highlight helpers ================= */

    private function shinglePositions(array $tokens, int $n): array
    {
        $N = count($tokens);
        $map = [];
        for ($i = 0; $i <= $N - $n; $i++) {
            $h = (string) crc32(implode(' ', array_slice($tokens, $i, $n)));
            $map[$h][] = $i;
        }
        return $map;
    }

    private function makeShingles(array $tokens, int $n = 3): array
    {
        $N = count($tokens);
        $set = [];
        for ($i = 0; $i <= $N - $n; $i++) {
            $set[(string) crc32(implode(' ', array_slice($tokens, $i, $n)))] = true;
        }
        return $set;
    }

    private function matchRanges(array $docShinglePos, array $srcTokens, int $n): array
    {
        $M = count($srcTokens);
        $srcShingles = [];
        for ($j = 0; $j <= $M - $n; $j++) {
            $h = (string) crc32(implode(' ', array_slice($srcTokens, $j, $n)));
            $srcShingles[$h] = true;
        }

        $marks = [];
        foreach ($srcShingles as $h => $_) {
            if (!isset($docShinglePos[$h])) continue;
            foreach ($docShinglePos[$h] as $pos) $marks[] = [$pos, $pos + $n - 1];
        }
        if (empty($marks)) return [];

        usort($marks, fn($a, $b) => $a[0] <=> $b[0]);
        $merged = [];
        [$curA, $curB] = $marks[0];
        for ($i = 1; $i < count($marks); $i++) {
            [$a, $b] = $marks[$i];
            if ($a <= $curB + 1) $curB = max($curB, $b);
            else { $merged[] = [$curA, $curB]; [$curA, $curB] = [$a, $b]; }
        }
        $merged[] = [$curA, $curB];
        return $merged;
    }

    private function countTokensCovered(array $ranges): int
    {
        $sum = 0;
        foreach ($ranges as [$a, $b]) $sum += ($b - $a + 1);
        return $sum;
    }

    private function makeSnippets(array $tokens, array $ranges, int $context = 15, int $max = 2): array
    {
        $snips = [];
        foreach ($ranges as [$a, $b]) {
            $start = max(0, $a - $context);
            $end   = min(count($tokens) - 1, $b + $context);
            $slice = array_slice($tokens, $start, $end - $start + 1);
            $snips[] = '… ' . htmlspecialchars(implode(' ', $slice)) . ' …';
            if (count($snips) >= $max) break;
        }
        return $snips;
    }

    /* ===================== Ekstraksi teks ===================== */

    private function extractText(Document $document): string
    {
        $path = storage_path("app/public/{$document->filename}");
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        try {
            if ($ext === 'pdf') {
                $bin = env('PDF_TO_TEXT_BINARY');
                $pdf = $bin ? new Pdf($bin) : new Pdf();
                return $pdf->setPdf($path)->text();
            }
            if (in_array($ext, ['doc', 'docx'])) {
                $phpWord = IOFactory::load($path);
                $text = '';
                foreach ($phpWord->getSections() as $section) {
                    foreach ($section->getElements() as $el) {
                        if ($el instanceof \PhpOffice\PhpWord\Element\Text) {
                            $text .= $el->getText() . "\n";
                        } elseif ($el instanceof \PhpOffice\PhpWord\Element\TextRun) {
                            foreach ($el->getElements() as $subEl) {
                                if ($subEl instanceof \PhpOffice\PhpWord\Element\Text) {
                                    $text .= $subEl->getText() . "\n";
                                }
                            }
                        } elseif ($el instanceof \PhpOffice\PhpWord\Element\Table) {
                            foreach ($el->getRows() as $row) {
                                foreach ($row->getCells() as $cell) {
                                    foreach ($cell->getElements() as $cellEl) {
                                        if ($cellEl instanceof \PhpOffice\PhpWord\Element\Text) {
                                            $text .= $cellEl->getText() . "\n";
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                return $text;
            }
            if ($ext === 'txt') return file_get_contents($path) ?: '';
        } catch (\Throwable $e) {
            report($e);
        }
        return file_get_contents($path) ?: '';
    }

    private function normalize(string $text): string
    {
        $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        $text = mb_strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }

    /* ===================== arXiv API ===================== */

    private function topKeywords(array $tokens, int $k = 8): array
    {
        static $stop = ['the','and','for','with','that','this','are','was','were','have','has','had','of','in','on','to','a','an','is','it','as','by','at','from','be','or','we','our','their','your'];
        $freq = [];
        foreach ($tokens as $t) {
            if (is_numeric($t)) continue;
            if (mb_strlen($t) < 3) continue;
            if (in_array($t, $stop, true)) continue;
            $freq[$t] = ($freq[$t] ?? 0) + 1;
        }
        arsort($freq);
        return array_slice(array_keys($freq), 0, $k);
    }

    private function searchArxivPaged(string $query, int $maxTotal = 60, int $pageSize = 30): array
    {
        $query = trim($query);
        if ($query === '') return [];
        $client = new Client([
            'base_uri' => 'http://export.arxiv.org/api/',
            'timeout'  => 20,
            'headers'  => [
                'User-Agent' => 'PlagiarismChecker/1.0 (+mailto:you@example.com)',
                'Accept'     => 'application/atom+xml',
            ],
        ]);

        $outMap = [];
        for ($start = 0; $start < $maxTotal; $start += $pageSize) {
            $limit = min($pageSize, $maxTotal - $start);
            $resp = $client->get('query', [
                'query' => [
                    'search_query' => sprintf('cat:cs* AND all:"%s"', $query),
                    'start'        => $start,
                    'max_results'  => $limit,
                    'sortBy'       => 'relevance',
                    'sortOrder'    => 'descending',
                ],
            ]);
            $xml = @simplexml_load_string((string)$resp->getBody());
            if ($xml === false) break;
            foreach ($xml->entry as $entry) {
                $title   = trim((string)$entry->title);
                $summary = trim((string)$entry->summary);
                $url     = $this->forceHttps(trim((string)$entry->id));
                $pdfUrl  = null;
                foreach ($entry->link as $link) {
                    $href = (string)$link['href'];
                    $type = (string)$link['type'];
                    $ttl  = (string)$link['title'];
                    if ($ttl==='pdf' || $type==='application/pdf' || str_contains($href,'/pdf/')) {
                        $pdfUrl = $this->forceHttps($href);
                    }
                }
                $key = $url ?: ($pdfUrl ?? md5($title.$summary));
                $outMap[$key] = [
                    'title'   => $title,
                    'summary' => $summary,
                    'url'     => $this->forceHttps($url ?: ($pdfUrl ?? '')),
                    'pdf_url' => $pdfUrl ? $this->forceHttps($pdfUrl) : null,
                ];
            }
            if (count($xml->entry) == 0) break;
        }
        return array_values($outMap);
    }

    private function searchArxiv(string $query, int $maxResults = 10): array
    {
        $q = trim($query);
        if ($q === '') return [];

        $cacheKey = 'arxiv:' . md5($q) . ":{$maxResults}";
        if ($cached = Cache::get($cacheKey)) return $cached;

        $client = new Client([
            'base_uri' => 'http://export.arxiv.org/api/',
            'timeout'  => 20,
            'headers'  => [
                'User-Agent' => 'PlagiarismChecker/1.0 (+mailto:you@example.com)',
                'Accept'     => 'application/atom+xml',
            ],
        ]);

        $searchQuery = sprintf('cat:cs* AND all:"%s"', $q);
        $resp = $client->get('query', [
            'query' => [
                'search_query' => $searchQuery,
                'start'        => 0,
                'max_results'  => $maxResults,
                'sortBy'       => 'relevance',
                'sortOrder'    => 'descending',
            ],
        ]);

        $xml = @simplexml_load_string((string)$resp->getBody());
        if ($xml === false) return [];

        $out = [];
        foreach ($xml->entry as $entry) {
            $title   = trim((string)$entry->title);
            $summary = trim((string)$entry->summary);
            $url     = $this->forceHttps(trim((string)$entry->id));
            $pdfUrl  = null;
            foreach ($entry->link as $link) {
                $href = (string)$link['href'];
                $type = (string)$link['type'];
                $titleAttr = (string)$link['title'];
                if ($titleAttr==='pdf' || $type==='application/pdf' || str_contains($href,'/pdf/')) {
                    $pdfUrl = $this->forceHttps($href);
                    break;
                }
            }
            $out[] = [
                'title'   => $title,
                'summary' => $summary,
                'url'     => $this->forceHttps($url ?: ($pdfUrl ?? '')),
                'pdf_url' => $pdfUrl ? $this->forceHttps($pdfUrl) : null,
            ];
        }
        Cache::put($cacheKey, $out, now()->addMinutes(15));
        return $out;
    }

    /* ===================== Similarity ===================== */

    private function tokenize(string $text): array
    {
        if ($text === '') return [];
        $tokens = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        return $tokens ?: [];
    }

    private function jaccard(array $setA, array $setB): float
    {
        if (!$setA || !$setB) return 0.0;
        $intersect = 0;
        $small = (count($setA) < count($setB)) ? $setA : $setB;
        $large = ($small === $setA) ? $setB : $setA;
        foreach ($small as $k => $_) if (isset($large[$k])) $intersect++;
        $union = count($setA) + count($setB) - $intersect;
        return $union > 0 ? $intersect / $union : 0.0;
    }

    private function tfidfCosine(string $docA, string $docB): float
    {
        $ta = $this->tokenize($docA);
        $tb = $this->tokenize($docB);
        if (!$ta || !$tb) return 0.0;

        $fa = array_count_values($ta);
        $fb = array_count_values($tb);

        $vocab = array_unique(array_merge(array_keys($fa), array_keys($fb)));

        $dot = 0.0; $na = 0.0; $nb = 0.0;
        foreach ($vocab as $term) {
            $wa = isset($fa[$term]) ? log(1 + $fa[$term]) : 0.0;
            $wb = isset($fb[$term]) ? log(1 + $fb[$term]) : 0.0;
            $dot += $wa * $wb; $na += $wa*$wa; $nb += $wb*$wb;
        }
        if ($na == 0.0 || $nb == 0.0) return 0.0;
        return $dot / (sqrt($na) * sqrt($nb));
    }

    private function forceHttps(string $url): string
    {
        if (str_starts_with($url, 'http://')) $url = 'https://' . substr($url, 7);
        $url = str_replace('export.arxiv.org', 'arxiv.org', $url);
        return $url;
    }

    /** Pecah raw jadi token + offset karakter asli. */
    private function docTokensWithOffsets(string $raw): array
    {
        $len = mb_strlen($raw);
        $tokens = []; $offsets = [];
        $buf=''; $tStart=null;

        for ($i=0; $i<$len; $i++) {
            $ch = mb_substr($raw, $i, 1);
            if (preg_match('/[A-Za-z0-9]/u', $ch)) {
                if ($tStart === null) $tStart = $i;
                $buf .= mb_strtolower($ch);
            } else {
                if ($tStart !== null) {
                    $tokens[]  = $buf;
                    $offsets[] = [$tStart, $i-1];
                    $buf=''; $tStart=null;
                }
            }
        }
        if ($tStart !== null) {
            $tokens[]  = $buf;
            $offsets[] = [$tStart, $len-1];
        }
        return ['tokens'=>$tokens,'offsets'=>$offsets];
    }

    /** Merge interval [a,b] yang tumpang tindih/kontigu (berbasis char index). */
    private function mergeIntervals(array $ranges): array
    {
        if (empty($ranges)) return [];
        usort($ranges, fn($x,$y)=>$x[0]<=>$y[0]);
        $out=[]; [$A,$B]=$ranges[0];
        for ($i=1;$i<count($ranges);$i++){
            [$a,$b]=$ranges[$i];
            if ($a <= $B+1) $B = max($B,$b);
            else { $out[] = [$A,$B]; [$A,$B]=[$a,$b]; }
        }
        $out[] = [$A,$B];
        return $out;
    }

    /** Build segmen [owner,startChar,endChar] dari owner per-token. */
    private function buildCharSegments(string $raw, array $offsets, array $coverageOwner): array
    {
        $lenRaw = mb_strlen($raw);
        $N = count($offsets);
        $segs = [];

        if ($N === 0) return [[-1, 0, max(0,$lenRaw-1)]];

        if ($offsets[0][0] > 0) $segs[] = [-1, 0, $offsets[0][0]-1];

        $curOwner = $coverageOwner[0] ?? -1;
        $segStartTok = 0;
        for ($i=1; $i<$N; $i++) {
            if (($coverageOwner[$i] ?? -1) !== $curOwner) {
                $startChar = $offsets[$segStartTok][0];
                $endChar   = $offsets[$i][0]-1;
                $segs[] = [$curOwner, $startChar, max($startChar,$endChar)];
                $curOwner = $coverageOwner[$i] ?? -1;
                $segStartTok = $i;
            }
        }
        $startChar = $offsets[$segStartTok][0];
        $endChar   = $offsets[$N-1][1];
        $segs[] = [$curOwner, $startChar, $endChar];

        if ($offsets[$N-1][1] < $lenRaw-1) $segs[] = [-1, $offsets[$N-1][1]+1, $lenRaw-1];

        // merge berurutan dengan owner sama
        $merged=[];
        foreach ($segs as $seg){
            if (!$merged){ $merged[]=$seg; continue; }
            [$own,$a,$b]=$seg; [$pOwn,$pA,$pB]=$merged[count($merged)-1];
            if ($own===$pOwn && $a<= $pB+1) $merged[count($merged)-1]=[$own,$pA,max($pB,$b)];
            else $merged[]=$seg;
        }
        return $merged;
    }

    /** Render HTML dari raw + segmen; warna hanya untuk owner yang ada di Top-K. */
private function renderColoredHtmlFromRaw(string $raw, array $charSegments, array $topK): string
{
    $out = '';

    // map owner -> color
    $colors = [];
    foreach ($topK as $i => $s) {
        $colors[$i] = $s['color'] ?? '#fff59d';
    }

    $len = mb_strlen($raw);
    foreach ($charSegments as [$own, $a, $b]) {
        $a = max(0, $a);
        $b = min($len - 1, $b);
        if ($a > $b) {
            continue;
        }

        $chunk = mb_substr($raw, $a, $b - $a + 1);
        $chunk = htmlspecialchars($chunk, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // owner di luar Top-K: biarkan tanpa warna
        if ($own === -1 || !array_key_exists($own, $colors)) {
            $out .= $chunk;
        } else {
            $out .= '<span style="background:' . $colors[$own] . '">' . $chunk . '</span>';
        }
    }

    // pertahankan spasi & newline seperti asal
    return '<div style="white-space:pre-wrap;line-height:1.7;font-size:12px;">' . $out . '</div>';
}


    /** Snippet dari raw-char ranges. */
    private function makeSnippetsFromChar(string $raw, array $charRanges, int $contextChars = 120, int $max = 2): array
    {
        $snips = [];
        $len = mb_strlen($raw);
        foreach ($charRanges as [$a,$b]) {
            $start = max(0, $a - $contextChars);
            $end   = min($len - 1, $b + $contextChars);
            $slice = mb_substr($raw, $start, $end - $start + 1);
            $slice = preg_replace('/\s+/u', ' ', $slice);
            $snips[] = '… ' . htmlspecialchars(trim($slice), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') . ' …';
            if (count($snips) >= $max) break;
        }
        return $snips;
    }

    /* ============ Helpers: embeddings cache wrapper ============ */

    private function embedText(string $text): array
    {
        if (!$this->embed) return [];
        $key = md5($text);
        if (isset($this->embedCache[$key])) return $this->embedCache[$key];
        // Bisa dibatasi panjang agar ringan
        $text = mb_substr($text, 0, 5000);
        $vec = $this->embed->embed($text);
        return $this->embedCache[$key] = ($vec ?: []);
    }
}
