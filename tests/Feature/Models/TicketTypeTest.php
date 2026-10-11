<?php

use App\Models\Event;
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
