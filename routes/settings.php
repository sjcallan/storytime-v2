<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\ProfilesController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('settings/profile-photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo.update');
    Route::delete('settings/profile-photo', [ProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    // Profiles management
    Route::get('settings/profiles', [ProfilesController::class, 'index'])->name('profiles.index');
    Route::post('settings/profiles', [ProfilesController::class, 'store'])->name('profiles.store');
    Route::patch('settings/profiles/{profile}', [ProfilesController::class, 'update'])->name('profiles.update');
    Route::delete('settings/profiles/{profile}', [ProfilesController::class, 'destroy'])->name('profiles.destroy');
    Route::post('settings/profiles/{profile}/default', [ProfilesController::class, 'setDefault'])->name('profiles.set-default');
    Route::post('settings/profiles/{profile}/image', [ProfilesController::class, 'updateImage'])->name('profiles.image.update');
    Route::delete('settings/profiles/{profile}/image', [ProfilesController::class, 'destroyImage'])->name('profiles.image.destroy');
    Route::post('profiles/{profile}/switch', [ProfilesController::class, 'switchProfile'])->name('profiles.switch');
});
