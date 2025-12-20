<?php

use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\ChapterController;
use App\Http\Controllers\Api\CharacterController;
use App\Http\Controllers\Api\CharacterExtractController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\ConversationMessageController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\GenreController;
use App\Http\Controllers\Api\ReadingHistoryController;
use App\Http\Controllers\Api\ReadingLogController;
use App\Http\Controllers\Api\RequestLogController;
use App\Http\Controllers\Api\TranscribeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Authenticated API routes
Route::middleware('auth:sanctum')->group(function () {
    // Resource routes
    Route::apiResource('books', BookController::class);
    Route::apiResource('chapters', ChapterController::class);
    Route::apiResource('characters', CharacterController::class);
    Route::apiResource('conversations', ConversationController::class);
    Route::apiResource('conversation-messages', ConversationMessageController::class);
    Route::apiResource('genres', GenreController::class);
    Route::apiResource('reading-logs', ReadingLogController::class);
    Route::apiResource('request-logs', RequestLogController::class);

    // Custom endpoints
    Route::post('transcribe', [TranscribeController::class, 'transcribe'])->name('api.transcribe');
    Route::post('extract-characters', [CharacterExtractController::class, 'extract'])->name('api.extract-characters');

    // Book metadata generation
    Route::post('books/{book}/generate-metadata', [BookController::class, 'generateMetadata'])->name('api.books.generate-metadata');

    // Chapter endpoints
    Route::get('books/{book}/chapters/{chapterNumber}', [ChapterController::class, 'getByBookAndSort'])
        ->whereNumber('chapterNumber')
        ->name('api.books.chapters.show');
    Route::post('books/{book}/chapters/generate', [ChapterController::class, 'generateNext'])->name('api.books.chapters.generate');
    Route::get('books/{book}/chapters/suggest-prompt', [ChapterController::class, 'suggestPrompt'])->name('api.books.chapters.suggest-prompt');

    // Reading history endpoints
    Route::post('books/{book}/reading-history/open', [ReadingHistoryController::class, 'open'])->name('api.books.reading-history.open');
    Route::post('books/{book}/reading-history/advance', [ReadingHistoryController::class, 'advanceChapter'])->name('api.books.reading-history.advance');

    // Favorite endpoints
    Route::get('books/{book}/favorite', [FavoriteController::class, 'check'])->name('api.books.favorite.check');
    Route::post('books/{book}/favorite', [FavoriteController::class, 'store'])->name('api.books.favorite.store');
    Route::delete('books/{book}/favorite', [FavoriteController::class, 'destroy'])->name('api.books.favorite.destroy');
});
