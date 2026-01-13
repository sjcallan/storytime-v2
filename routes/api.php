<?php

use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\ChapterController;
use App\Http\Controllers\Api\CharacterChatController;
use App\Http\Controllers\Api\CharacterController;
use App\Http\Controllers\Api\CharacterExtractController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\ConversationMessageController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\GenreController;
use App\Http\Controllers\Api\ImageController;
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

    // Image resource and custom endpoints
    Route::apiResource('images', ImageController::class)->only(['index', 'show', 'destroy']);
    Route::post('images/{image}/regenerate', [ImageController::class, 'regenerate'])->name('api.images.regenerate');
    Route::post('images/{image}/cancel', [ImageController::class, 'cancel'])->name('api.images.cancel');
    Route::patch('images/{image}/prompt', [ImageController::class, 'updatePrompt'])->name('api.images.update-prompt');

    // Create images for entities
    Route::post('books/{book}/images/cover', [ImageController::class, 'createBookCover'])->name('api.books.images.cover');
    Route::post('books/{book}/images/custom', [ImageController::class, 'createCustomImage'])->name('api.books.images.custom');
    Route::post('chapters/{chapter}/images/header', [ImageController::class, 'createChapterHeader'])->name('api.chapters.images.header');
    Route::post('chapters/{chapter}/images/inline', [ImageController::class, 'createChapterInline'])->name('api.chapters.images.inline');
    Route::post('characters/{character}/images/portrait', [ImageController::class, 'createCharacterPortrait'])->name('api.characters.images.portrait');

    // Custom endpoints
    Route::post('transcribe', [TranscribeController::class, 'transcribe'])->name('api.transcribe');
    Route::post('extract-characters', [CharacterExtractController::class, 'extract'])->name('api.extract-characters');

    // Book metadata generation
    Route::post('books/{book}/generate-metadata', [BookController::class, 'generateMetadata'])->name('api.books.generate-metadata');

    // Book cover regeneration
    Route::post('books/{book}/regenerate-cover', [BookController::class, 'regenerateCover'])->name('api.books.regenerate-cover');

    // Plot inspiration generation
    Route::post('books/inspire-plot', [BookController::class, 'inspirePlot'])->name('api.books.inspire-plot');

    // Chapter endpoints
    Route::get('books/{book}/chapters/{chapterNumber}', [ChapterController::class, 'getByBookAndSort'])
        ->whereNumber('chapterNumber')
        ->name('api.books.chapters.show');
    Route::post('books/{book}/chapters/generate', [ChapterController::class, 'generateNext'])->name('api.books.chapters.generate');
    Route::get('books/{book}/chapters/suggest-prompt', [ChapterController::class, 'suggestPrompt'])->name('api.books.chapters.suggest-prompt');
    Route::post('books/{book}/chapters/{chapter}/regenerate-image', [ChapterController::class, 'regenerateImage'])->name('api.books.chapters.regenerate-image');
    Route::post('books/{book}/chapters/{chapter}/regenerate-header-image', [ChapterController::class, 'regenerateHeaderImage'])->name('api.books.chapters.regenerate-header-image');
    Route::post('books/{book}/chapters/{chapter}/generate-header-image', [ChapterController::class, 'generateHeaderImage'])->name('api.books.chapters.generate-header-image');
    Route::post('books/{book}/chapters/{chapter}/retry-header-image', [ChapterController::class, 'retryHeaderImage'])->name('api.books.chapters.retry-header-image');
    Route::post('books/{book}/chapters/{chapter}/cancel-header-image', [ChapterController::class, 'cancelHeaderImage'])->name('api.books.chapters.cancel-header-image');
    Route::post('books/{book}/chapters/{chapter}/retry-inline-image', [ChapterController::class, 'retryInlineImage'])->name('api.books.chapters.retry-inline-image');
    Route::post('books/{book}/chapters/{chapter}/cancel-inline-image', [ChapterController::class, 'cancelInlineImage'])->name('api.books.chapters.cancel-inline-image');
    Route::post('books/{book}/chapters/{chapter}/edit', [ChapterController::class, 'editContent'])->name('api.books.chapters.edit');
    Route::post('books/{book}/chapters/{chapter}/rewrite', [ChapterController::class, 'rewriteContent'])->name('api.books.chapters.rewrite');

    // Reading history endpoints
    Route::post('books/{book}/reading-history/open', [ReadingHistoryController::class, 'open'])->name('api.books.reading-history.open');
    Route::post('books/{book}/reading-history/advance', [ReadingHistoryController::class, 'advanceChapter'])->name('api.books.reading-history.advance');

    // Favorite endpoints
    Route::get('books/{book}/favorite', [FavoriteController::class, 'check'])->name('api.books.favorite.check');
    Route::post('books/{book}/favorite', [FavoriteController::class, 'store'])->name('api.books.favorite.store');
    Route::delete('books/{book}/favorite', [FavoriteController::class, 'destroy'])->name('api.books.favorite.destroy');

    // Character chat endpoints
    Route::get('characters/{character}/chat', [CharacterChatController::class, 'getOrCreateConversation'])->name('api.characters.chat.conversation');
    Route::post('conversations/{conversation}/chat', [CharacterChatController::class, 'sendMessage'])->name('api.conversations.chat.send');
    Route::get('conversations/{conversation}/history', [CharacterChatController::class, 'getHistory'])->name('api.conversations.chat.history');

    // Character portrait regeneration
    Route::post('characters/{character}/regenerate-portrait', [CharacterController::class, 'regeneratePortrait'])->name('api.characters.regenerate-portrait');

    // Character portrait upload
    Route::post('characters/{character}/upload-portrait', [CharacterController::class, 'uploadPortrait'])->name('api.characters.upload-portrait');
});
