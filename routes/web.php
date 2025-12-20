<?php

use App\Models\ReadingHistory;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    $user = auth()->user();
    $currentProfileId = session('current_profile_id');

    // Get all books for the user's current profile grouped by genre, sorted by created_at desc
    $books = \App\Models\Book::query()
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
        ->with('book')
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
            'cover_image' => $history->book->cover_image,
            'current_chapter_number' => $history->current_chapter_number,
            'last_read_at' => $history->last_read_at->toISOString(),
        ]);

    return Inertia::render('Dashboard', [
        'booksByGenre' => $books,
        'recentlyRead' => $recentlyRead,
        'userName' => $user->name,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
