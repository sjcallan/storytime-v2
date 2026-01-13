<?php

use App\Http\Controllers\LegalController;
use App\Models\Favorite;
use App\Models\ReadingHistory;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('/terms', [LegalController::class, 'terms'])->name('terms');
Route::get('/privacy', [LegalController::class, 'privacy'])->name('privacy');

Route::get('dashboard', function () {
    $user = auth()->user();
    $currentProfileId = session('current_profile_id');

    // Get all books for the user's current profile grouped by genre, sorted by created_at desc
    $books = \App\Models\Book::query()
        ->with('coverImage')
        ->where('user_id', $user->id)
        ->when($currentProfileId, function ($query, $profileId) {
            return $query->where('profile_id', $profileId);
        })
        ->whereNotNull('genre')
        ->latest('created_at')
        ->get()
        ->groupBy('genre');

    // Get recently read books from reading history
    $recentlyRead = ReadingHistory::query()
        ->with('book.coverImage')
        ->where('user_id', $user->id)
        ->when($currentProfileId, function ($query, $profileId) {
            return $query->where('profile_id', $profileId);
        })
        ->whereHas('book')
        ->latest('last_read_at')
        ->limit(10)
        ->get()
        ->map(fn ($history) => [
            'id' => $history->book->id,
            'title' => $history->book->title,
            'genre' => $history->book->genre,
            'author' => $history->book->author,
            'age_level' => $history->book->age_level,
            'status' => $history->book->status,
            'cover_image_url' => $history->book->cover_image_url,
            'current_chapter_number' => $history->current_chapter_number,
            'last_read_at' => $history->last_read_at->toISOString(),
        ]);

    // Get favorite books sorted by reading history last_read_at desc
    $favorites = Favorite::query()
        ->with(['book.coverImage'])
        ->where('user_id', $user->id)
        ->when($currentProfileId, function ($query, $profileId) {
            return $query->where('profile_id', $profileId);
        })
        ->whereHas('book')
        ->get()
        ->map(function ($favorite) use ($user, $currentProfileId) {
            // Get the reading history for this book to get last_read_at
            $readingHistory = ReadingHistory::query()
                ->where('user_id', $user->id)
                ->where('book_id', $favorite->book_id)
                ->when($currentProfileId, function ($query, $profileId) {
                    return $query->where('profile_id', $profileId);
                })
                ->first();

            return [
                'id' => $favorite->book->id,
                'title' => $favorite->book->title,
                'genre' => $favorite->book->genre,
                'author' => $favorite->book->author,
                'age_level' => $favorite->book->age_level,
                'status' => $favorite->book->status,
                'cover_image_url' => $favorite->book->cover_image_url,
                'current_chapter_number' => $readingHistory?->current_chapter_number ?? 1,
                'last_read_at' => $readingHistory?->last_read_at?->toISOString() ?? $favorite->created_at->toISOString(),
            ];
        })
        ->sortByDesc('last_read_at')
        ->values();

    return Inertia::render('Dashboard', [
        'booksByGenre' => $books,
        'recentlyRead' => $recentlyRead,
        'favorites' => $favorites,
        'userName' => $user->name,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
