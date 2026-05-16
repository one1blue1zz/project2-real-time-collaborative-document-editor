<?php

use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('documents.index');
});

Route::resource('documents', DocumentController::class);
Route::put('documennts/{document}/restore/version', [DocumentController::class, 'restoreVersion'])->name('documents.restore');
Route::get('documents/{document}/history', [DocumentController::class, 'history'])->name('documents.history');