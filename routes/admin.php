<?php

use App\Http\Controllers\Admin\UsersController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->group(function () {
    Route::get('/', function () {
        return redirect()->route('admin.users.index');
    })->name('admin.index');

    Route::get('/users', [UsersController::class, 'index'])->name('admin.users.index');
});
