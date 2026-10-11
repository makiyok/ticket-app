<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $user = auth()->user();

        return redirect(match (true) {
            $user->isAdmin() => '/admin/events',
            $user->isOrganizer() => '/organizer/events',
            default => '/orders',
        });
    })->name('dashboard');
});

require __DIR__.'/settings.php';
