<?php

use App\Enums\UserRole;
use App\Models\User;

function registrationInput(array $overrides = []): array
{
    return [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'customer',
        ...$overrides,
    ];
}

test('users can register as an organizer', function () {
    $this->post(route('register.store'), registrationInput(['role' => 'organizer']))
        ->assertSessionHasNoErrors();

    expect(User::firstWhere('email', 'test@example.com')->role)->toBe(UserRole::Organizer);
});

test('users can register as a customer', function () {
    $this->post(route('register.store'), registrationInput())
        ->assertSessionHasNoErrors();

    expect(User::firstWhere('email', 'test@example.com')->role)->toBe(UserRole::Customer);
});

test('admin cannot be chosen at registration', function (string $role) {
    $this->post(route('register.store'), registrationInput(['role' => $role]))
        ->assertSessionHasErrors('role');

    expect(User::count())->toBe(0);
    $this->assertGuest();
})->with(['admin', 'unknown']);

test('role is required at registration', function () {
    $this->post(route('register.store'), registrationInput(['role' => '']))
        ->assertSessionHasErrors('role');
});

test('role labels are in Japanese', function () {
    expect(UserRole::Customer->label())->toBe('参加者')
        ->and(UserRole::Organizer->label())->toBe('主催者')
        ->and(UserRole::Admin->label())->toBe('管理者');
});

test('role helper methods follow the role', function () {
    expect(User::factory()->admin()->make()->isAdmin())->toBeTrue()
        ->and(User::factory()->organizer()->make()->isOrganizer())->toBeTrue()
        ->and(User::factory()->make()->isCustomer())->toBeTrue()
        ->and(User::factory()->organizer()->make()->isAdmin())->toBeFalse();
});

test('an invalid role shows an error on the registration form', function () {
    $this->from(route('register'))
        ->followingRedirects()
        ->post(route('register.store'), registrationInput(['role' => 'admin']))
        ->assertSee(__('validation.in', ['attribute' => __('validation.attributes.role')]));
});
