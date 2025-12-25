<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\PinController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\ProfilesController;
use App\Http\Controllers\Settings\ThemeController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use App\Http\Controllers\Settings\UsageController;
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

    // PIN routes
    Route::post('settings/pin', [PinController::class, 'store'])->name('pin.store');
    Route::delete('settings/pin', [PinController::class, 'destroy'])->name('pin.destroy');
    Route::post('settings/pin/verify', [PinController::class, 'verify'])->name('pin.verify');
    Route::get('settings/pin/status', [PinController::class, 'status'])->name('pin.status');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance.edit');

    Route::get('settings/usage', [UsageController::class, 'index'])->name('usage.index');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    // Profile selection page
    Route::get('profiles/select', [ProfilesController::class, 'select'])->name('profiles.select');

    // Profiles management
    Route::get('settings/profiles', [ProfilesController::class, 'index'])->name('profiles.index');
    Route::post('settings/profiles', [ProfilesController::class, 'store'])->name('profiles.store');
    Route::patch('settings/profiles/{profile}', [ProfilesController::class, 'update'])->name('profiles.update');
    Route::delete('settings/profiles/{profile}', [ProfilesController::class, 'destroy'])->name('profiles.destroy');
    Route::post('settings/profiles/{profile}/default', [ProfilesController::class, 'setDefault'])->name('profiles.set-default');
    Route::post('settings/profiles/{profile}/image', [ProfilesController::class, 'updateImage'])->name('profiles.image.update');
    Route::delete('settings/profiles/{profile}/image', [ProfilesController::class, 'destroyImage'])->name('profiles.image.destroy');
    Route::post('settings/profiles/{profile}/generate-image', [ProfilesController::class, 'generateImage'])->name('profiles.image.generate');
    Route::post('profiles/{profile}/switch', [ProfilesController::class, 'switchProfile'])->name('profiles.switch');

    // Theme customization routes
    Route::post('themes', [ThemeController::class, 'store'])->name('themes.store');
    Route::patch('themes/{theme}', [ThemeController::class, 'update'])->name('themes.update');
    Route::delete('themes/{theme}', [ThemeController::class, 'destroy'])->name('themes.destroy');
    Route::post('themes/active', [ThemeController::class, 'setActive'])->name('themes.set-active');
    Route::post('themes/generate-background', [ThemeController::class, 'generateBackgroundImage'])->name('themes.generate-background');
    Route::post('themes/background-image', [ThemeController::class, 'setBackgroundImage'])->name('themes.set-background-image');
});
