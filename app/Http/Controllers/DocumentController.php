<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Events\DocumentContentUpdate;
use App\Events\UserPresence;

class DocumentController extends Controller
{
    public function index()
    {
        $documents = Document::latest()->get();
        return view('documents.index', compact('documents'));
    }

    public function create()
    {
        return view('documents.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
        ]);

        Document::create([
            'title' => $request->title,
            'content' => $request->content ?? '',
            'current_version' => 1,
            'status' => 'available',
        ]);

        return redirect()->route('documents.index')->with('success', 'Dokumen berhasil dibuat!');
    }

    public function edit(int $id)
    {
        $document = Document::findOrFail($id);
        return view('documents.edit', compact('document'));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $document = Document::findOrFail($id);
        
        $document->update([
            'content' => $request->content,
            'current_version' => $document->current_version + 1,
        ]);

        // Broadcast ke semua user yang sedang mengedit dokumen ini
        broadcast(new DocumentContentUpdate(
            $document->toArray(),
            $request->content,
            $request->userId,
            $request->userName
        ));

        return response()->json(['success' => true, 'version' => $document->current_version]);
    }

    public function history(int $id)
    {
        $document = Document::findOrFail($id);
        
        $versions = collect([]);
        for ($i = 1; $i <= $document->current_version; $i++) {
            $versions->push((object)[
                'version' => $i,
                'content' => $i == $document->current_version ? $document->content : 'Konten versi ' . $i,
                'created_at' => $document->created_at,
                'user_name' => 'System',
            ]);
        }
        
        return view('documents.history', compact('document', 'versions'));
    }

    public function destroy(int $id)
    {
        Document::findOrFail($id)->delete();
        return redirect()->route('documents.index')->with('success', 'Dokumen berhasil dihapus!');
    }
}