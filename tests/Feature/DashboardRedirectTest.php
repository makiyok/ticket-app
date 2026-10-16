<?php

use App\Enums\UserRole;
use App\Models\User;

test('dashboard redirects by role', function (UserRole $role, string $path) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect($path);
})->with([
    'admin' => [UserRole::Admin, '/admin/events'],
    'organizer' => [UserRole::Organizer, '/organizer/events'],
    'customer' => [UserRole::Customer, '/orders'],
]);
