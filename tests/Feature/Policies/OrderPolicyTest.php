<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('only the buyer, the organizer of the event and admins can view an order', function (string $who, bool $allowed) {
    $users = [
        'buyer' => User::factory()->create(),
        'organizer' => User::factory()->organizer()->create(),
        'admin' => User::factory()->admin()->create(),
        'otherCustomer' => User::factory()->create(),
        'otherOrganizer' => User::factory()->organizer()->create(),
    ];
    $event = Event::factory()->published()->for($users['organizer'], 'organizer')->create();
    $order = Order::factory()->for($users['buyer'])->for($event)->create();

    expect(Gate::forUser($users[$who])->allows('view', $order))->toBe($allowed);
})->with([
    ['buyer', true],
    ['organizer', true],
    ['admin', true],
    ['otherCustomer', false],
    ['otherOrganizer', false],
]);
