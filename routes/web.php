<?php

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

    // Get all books for the user's current profile grouped by genre
    $books = \App\Models\Book::query()
        ->where('user_id', $user->id)
        ->when($currentProfileId, function ($query, $profileId) {
            return $query->where('profile_id', $profileId);
        })
        ->whereNotNull('genre')
        ->orderBy('last_opened_date', 'desc')
        ->get()
        ->groupBy('genre');

    return Inertia::render('Dashboard', [
        'booksByGenre' => $books,
        'userName' => $user->name,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
