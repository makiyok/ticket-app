<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;

test('isOnSale is true only within the sales period, boundaries included', function (string $now, bool $expected) {
    $this->travelTo('2030-01-15 12:00:00');

    $ticketType = TicketType::factory()->create([
        'sales_starts_at' => '2030-01-10 10:00:00',
        'sales_ends_at' => '2030-01-20 18:00:00',
    ]);

    $this->travelTo($now);

    expect($ticketType->fresh()->isOnSale())->toBe($expected);
})->with([
    'before the start' => ['2030-01-10 09:59:59', false],
    'exactly at the start' => ['2030-01-10 10:00:00', true],
    'within the period' => ['2030-01-15 12:00:00', true],
    'exactly at the end' => ['2030-01-20 18:00:00', true],
    'after the end' => ['2030-01-20 18:00:01', false],
]);

test('factory states reflect the sales period', function () {
    expect(TicketType::factory()->create()->isOnSale())->toBeTrue()
        ->and(TicketType::factory()->beforeSale()->create()->isOnSale())->toBeFalse()
        ->and(TicketType::factory()->afterSale()->create()->isOnSale())->toBeFalse();
});

test('a ticket type belongs to its event', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create();

    expect($ticketType->event->is($event))->toBeTrue()
        ->and($event->ticketTypes->pluck('id')->all())->toBe([$ticketType->id]);
});

test('price and capacity are returned as integers', function () {
    $ticketType = TicketType::factory()->create(['price' => 3000, 'capacity' => 50]);

    $fresh = TicketType::find($ticketType->id);

    expect($fresh->price)->toBeInt()->toBe(3000)
        ->and($fresh->capacity)->toBeInt()->toBe(50);
});

test('deleting an event deletes its ticket types', function () {
    $event = Event::factory()->create();
    TicketType::factory()->for($event)->count(2)->create();
    $other = TicketType::factory()->create();

    $event->delete();

    expect(TicketType::pluck('id')->all())->toBe([$other->id]);
});

test('remaining subtracts paid orders and pending orders created within 15 minutes', function () {
    $this->travelTo('2030-01-15 12:00:00');
    $ticketType = TicketType::factory()->create(['capacity' => 50]);
    $hold = fn (Order $order, int $quantity) => OrderItem::factory()->for($order)->for($ticketType)->create(['quantity' => $quantity]);

    $hold(Order::factory()->paid()->create(['created_at' => '2030-01-01 00:00:00']), 30);
    $hold(Order::factory()->create(['created_at' => '2030-01-15 11:45:00']), 5);
    $hold(Order::factory()->create(['created_at' => '2030-01-15 11:44:59']), 4);
    $hold(Order::factory()->cancelled()->create(), 3);
    $hold(Order::factory()->refunded()->create(), 2);
    OrderItem::factory()->for(Order::factory()->paid())->create(['quantity' => 1]);

    expect($ticketType->remaining())->toBe(15);
});

test('remaining never goes below zero', function () {
    $ticketType = TicketType::factory()->create(['capacity' => 2]);
    OrderItem::factory()->for(Order::factory()->paid())->for($ticketType)->create(['quantity' => 3]);

    expect($ticketType->remaining())->toBe(0);
});
