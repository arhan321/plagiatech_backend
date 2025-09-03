<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use App\Services\PlagiarismService;
use Illuminate\Support\Facades\Auth;

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

    // 🔹 cek plagiarisme
    $results = $plagiarism->checkPlagiarism($document);

    $highest = collect($results)->sortByDesc('similarity')->first();
    $similarity = $highest['similarity'] ?? 0;

    $document->update(['similarity' => $similarity]);

    return redirect()->route('dashboard')
        ->with('success', "Jurnal berhasil diupload! Similarity: {$similarity}%")
        ->with('plagiarism_results', $results);
}


public function check($id, PlagiarismService $plagiarism)
{
    $document = Document::findOrFail($id);

    // 🔹 cek plagiarism
    $results = $plagiarism->checkPlagiarism($document);

    // 🔹 cari similarity tertinggi
    $highest = collect($results)->sortByDesc('similarity')->first();

    $similarity = $highest['similarity'] ?? 0;

    // 🔹 simpan similarity utama
    $document->update(['similarity' => $similarity]);

    return redirect()->route('dashboard')
        ->with('success', "Pengecekan selesai! Similarity: {$similarity}%")
        ->with('plagiarism_results', $results);
}

    public function destroy($id)
    {
        $document = Document::findOrFail($id);
        $document->delete();

        return redirect()->route('dashboard')->with('success', 'Jurnal berhasil dihapus!');
    }
}
