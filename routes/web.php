<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;  // <-- TAMBAHKAN INI
use App\Http\Controllers\DocumentController;
use App\Events\CursorMoved;
use App\Events\UserPresence;

Route::post('/broadcast/cursor', function (Request $request) {
    broadcast(new CursorMoved(
        $request->documentId,
        $request->userId,
        $request->userName,
        $request->position
    ));
    return response()->json(['success' => true]);
});

Route::post('/broadcast/presence', function (Request $request) {
    broadcast(new UserPresence(
        $request->documentId,
        $request->userId,
        $request->userName,
        $request->action
    ));
    return response()->json(['success' => true]);
});

Route::get('/', function () {
    return view('welcome');
});

// Route resource untuk standard CRUD
Route::resource('documents', DocumentController::class);

// Tambah route custom untuk history
Route::get('/documents/{id}/history', [DocumentController::class, 'history'])->name('documents.history');