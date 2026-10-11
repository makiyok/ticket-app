<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'role:organizer,admin'])->get('/_role-test', fn () => 'ok');
});

test('allowed roles pass', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get('/_role-test')->assertOk();
})->with(['organizer', 'admin']);

test('other roles are forbidden', function () {
    $this->actingAs(User::factory()->create())
        ->get('/_role-test')->assertForbidden();
});

test('guests are forbidden', function () {
    $this->get('/_role-test')->assertForbidden();
});

test('customers are forbidden on an organizer-only route', function () {
    Route::middleware(['web', 'role:organizer'])->get('/_organizer-only', fn () => 'ok');

    $this->actingAs(User::factory()->create())
        ->get('/_organizer-only')->assertForbidden();

    $this->actingAs(User::factory()->organizer()->create())
        ->get('/_organizer-only')->assertOk();
});

test('an unknown role name throws instead of silently forbidding', function () {
    Route::middleware(['web', 'role:organiser'])->get('/_misspelled-role', fn () => 'ok');

    $this->withoutExceptionHandling()
        ->actingAs(User::factory()->organizer()->create())
        ->get('/_misspelled-role');
})->throws(ValueError::class);
