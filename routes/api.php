<?php

use App\Http\Controllers\DocumentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Document;
use App\Events\CursorMoved;
use App\Events\LockReleased;

Route::post('/documents/{document}/cursor', function ($documentId, Request $request) {
    $document = Document::findOrFail($documentId);
    broadcast(new CursorMoved($documentId, $request->userId, $request->position));
    return response()->json(['success' => true]);
});

Route::post('/documents/{document}/auto-save', function ($documentId, Request $request) {
    $document = Document::findOrFail($documentId);
    
    if ($document->isLocked() && $document->locked_by !== $request->session()->getId()) {
        return response()->json(['error' => 'Document locked'], 423);
    }
    
    $document->lock($request->session()->getId());
    $document->update([
        'title' => $request->title,
        'content' => $request->content
    ]);
    
    return response()->json(['success' => true]);
});

Route::post('/documents/{document}/release-lock', function ($documentId, Request $request) {
    $document = Document::findOrFail($documentId);
    $document->unlock();
    broadcast(new LockReleased($documentId, $request->session()->getId()));
    return response()->json(['success' => true]);
});