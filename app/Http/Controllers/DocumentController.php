<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class DocumentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $documents = Document::orderBy('updated_at', 'desc')->get();
        return view('documents.index', compact('documents'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('documents.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string'
        ]);

        $document = Document::create([
            'title' => $request->title,
            'content' => $request->content ?? '',
            'current_version' => 1
        ]);

        DocumentVersion::create([
            'document_id' => $document->id,
            'content' => $document->content,
            'version' => 1,
            'user_id' => session()->getId(),
            'user_name' => 'User_' . substr(session()->getId(), 0, 8),
            'changes' => ['action' => 'created']
        ]);

        return redirect()->route('documents.edit', $document)
            ->with('success', 'Document created successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Document $document)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Document $document)
    {
        $versions = $document->versions()->orderBy('version', 'desc')->get();
        return view('documents.edit', compact('document', 'versions'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Document $document)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
        ]);

        if ($document->isLocked() && $document->locked_by !== session()->getId()) {
            return response()->json([
                'error' => 'Document is being editted by another user',
                'locked_by' => $document->locked_by
            ], 423);
        }

        $oldContent = $document->content;

        $document->update([
            'title' => $request->title,
            'content' => $request->content,
            'current_version' => $document->current_version + 1
        ]);

        DocumentVersion::create([
            'document_id' => $document->id,
            'content' => $request->content,
            'version' => $document->current_version,
            'user_id' => session()->getId(),
            'user_name' => 'User_' . substr(session()->getId(), 0, 8),
            'changes' => $this->computeChanges($oldContent, $request->content)
        ]);

        return redirect()->route('documents.index')
            ->with('success', 'Document updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Document $document)
    {
       $document->delete();
       return redirect()->route('documents.index')
            ->with('success', 'Document deleted successfully!');
    }

    public function history(Document $document)
    {
        $versions = $document->versions()->orderBy('version', 'desc')->get();
        return view('documents.history', compact('document', 'versions'));
    }

    public function restoreVersion(Document $document, $version)
    {
        $versionRecord = $document->versions()->where('version', $version)->firstOrFail();

        $document->update([
            'content' => $versionRecord->content,
            'current_version' => $document->current_version + 1
        ]);

        DocumentVersion::create([
            'document_id' => $document->id,
            'content' => $versionRecord->content,
            'version' => $document->current_version,
            'user_id' => session()->getId(),
            'user_name' => 'User_' . substr(session()->getId(), 0, 8),
            'changes' => ['action' => 'restored', 'from_version' => $version]
        ]);

        return redirect()->route('documents.edit', $document)
            ->with('success', "Restored to version ($version)");
    }

    private function computeChanges($old, $new)
    {
        if ($old === $new) return ['action' => 'no_changes'];

        $oldWords = str_word_count($old, 1);
        $newWords = str_word_count($new, 1);

        return [
            'action' => 'edited',
            'old_length' => strlen($old),
            'new_length' => strlen($new) - strlen($old),
            'difference' => strlen($new) - strlen($old),
        ];
    }
}