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

    Route::middleware('role:organizer')->prefix('organizer/events')->name('organizer.events.')->group(function () {
        Route::livewire('/', 'pages::organizer.events.index')->name('index');
        Route::livewire('create', 'pages::organizer.events.create')->name('create');
        Route::livewire('{event}/edit', 'pages::organizer.events.edit')->name('edit');
    });
});

require __DIR__.'/settings.php';
