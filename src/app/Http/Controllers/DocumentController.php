<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use App\Services\PlagiarismService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Mpdf\Mpdf;

class DocumentController extends Controller
{
    public function index()
    {
        $documents = Document::where('user_id', Auth::id())->latest()->get();
        return view('dashboard', compact('documents'));
    }

    public function upload(Request $request, PlagiarismService $plagiarism)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'file'  => 'required|mimes:pdf,doc,docx,txt|max:10240',
        ]);

        $path = $request->file('file')->store('documents', 'public');

        $document = Document::create([
            'user_id'  => Auth::id(),
            'title'    => $request->title,
            'filename' => $path,
        ]);

        // 🔹 cek plagiarisme (PAKET)
        $pack = $plagiarism->checkPlagiarism($document);

        // 🔹 overall = union coverage (% token tertutup)
        $overall = $pack['overall'] ?? 0;
        $document->update(['similarity' => $overall]);

        return redirect()->route('dashboard')
            ->with('success', "Jurnal berhasil diupload! Similarity (gabungan): {$overall}%")
            // ⬇️ kirim HANYA daftar sumber ke tabel Blade
            ->with('plagiarism_results', $pack['sources'] ?? []);
    }

    public function check($id, PlagiarismService $plagiarism)
    {
        $document = Document::findOrFail($id);

        $pack = $plagiarism->checkPlagiarism($document);

        $overall = $pack['overall'] ?? 0;
        $document->update(['similarity' => $overall]);

        return redirect()->route('dashboard')
            ->with('success', "Pengecekan selesai! Similarity (gabungan): {$overall}%")
            ->with('plagiarism_results', $pack['sources'] ?? []);
    }

    public function destroy($id)
    {
        $document = Document::findOrFail($id);
        $document->delete();

        return redirect()->route('dashboard')->with('success', 'Jurnal berhasil dihapus!');
    }

    public function receipt($id, \App\Services\PlagiarismService $plagiarism)
    {
        $document = \App\Models\Document::findOrFail($id);

        $pack = $plagiarism->checkPlagiarism($document);
        if (empty($pack)) {
            // fallback kosong
            $pack = [
                'overall' => 0,
                'sources' => [],
                'colored_html' => '<em>Tidak ada indikasi plagiarisme signifikan.</em>',
                'token_count' => 0,
                'covered_count' => 0,
            ];
        }

        $overall = $pack['overall'];
        $risk = [
            'label' => $overall >= 70 ? 'Tinggi' : ($overall >= 40 ? 'Sedang' : 'Rendah'),
            'hex'   => $overall >= 70 ? '#dc3545' : ($overall >= 40 ? '#fd7e14' : '#28a745'),
        ];

        $html = view('reports.plagiarism_receipt_turnitin', [
            'user'         => Auth::user(), 
            'document'     => $document,
            'overall'      => $overall,
            'risk'         => $risk,
            'sources'      => $pack['sources'],
            'colored_html' => $pack['colored_html'],
            'token_count'  => $pack['token_count'],
            'covered_count' => $pack['covered_count'],
            'generated_at' => now()->timezone('Asia/Jakarta'),
        ])->render();

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => storage_path('app/mpdf-temp'),
            'margin_top' => 12,
            'margin_bottom' => 12,
            'margin_left' => 12,
            'margin_right' => 12,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);
        $mpdf->SetTitle('Plagiarism Receipt');
        $mpdf->WriteHTML($html);

        $filename = 'receipt-plagiarism-' . \Illuminate\Support\Str::slug($document->title ?: 'document') . '.pdf';
        return response($mpdf->Output($filename, \Mpdf\Output\Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
