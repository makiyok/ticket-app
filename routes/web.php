<?php

use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::events.index')->name('home');

Route::livewire('events/{event}', 'pages::events.show')->name('events.show');

Route::post('stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

Route::middleware('auth')->group(function () {
    Route::livewire('events/{event}/purchase', 'pages::events.purchase')->name('events.purchase');
    Route::livewire('orders', 'pages::orders.index')->name('orders.index');
    Route::livewire('orders/{order}/complete', 'pages::orders.complete')->name('orders.complete');
    Route::livewire('orders/{order}/cancel', 'pages::orders.cancel')->name('orders.cancel');
});

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
