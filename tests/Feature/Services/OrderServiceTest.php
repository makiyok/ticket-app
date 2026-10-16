<?php

use App\Enums\OrderStatus;
use App\Exceptions\PurchaseException;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Models\User;
use App\Services\OrderService;

test('creates a pending order with items priced at the current ticket prices', function () {
    $buyer = User::factory()->create();
    $event = Event::factory()->published()->create();
    $general = TicketType::factory()->for($event)->create(['price' => 3000, 'capacity' => 50]);
    $student = TicketType::factory()->for($event)->create(['price' => 1500, 'capacity' => 50]);

    $order = app(OrderService::class)->createOrder($buyer, $event, [$general->id => 2, $student->id => 1]);

    $order = $order->fresh();
    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->user_id)->toBe($buyer->id)
        ->and($order->event_id)->toBe($event->id)
        ->and($order->total_amount)->toBe(7500)
        ->and($order->paid_at)->toBeNull();

    expect($order->items()->orderBy('id')->get()->map->only(['ticket_type_id', 'quantity', 'unit_price'])->all())->toBe([
        ['ticket_type_id' => $general->id, 'quantity' => 2, 'unit_price' => 3000],
        ['ticket_type_id' => $student->id, 'quantity' => 1, 'unit_price' => 1500],
    ]);
});

test('rejects a quantity above the remaining count and creates no order', function () {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['name' => '一般', 'capacity' => 5]);
    OrderItem::factory()->for(Order::factory()->paid()->for($event))->for($ticketType)->create(['quantity' => 3]);

    expect(fn () => app(OrderService::class)->createOrder(User::factory()->create(), $event, [$ticketType->id => 3]))
        ->toThrow(PurchaseException::class, '「一般」の残り枚数が足りません（残り2枚）。');

    expect(Order::count())->toBe(1)
        ->and(OrderItem::count())->toBe(1);
});

test('accepts a quantity equal to the remaining count', function () {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['capacity' => 2]);

    $order = app(OrderService::class)->createOrder(User::factory()->create(), $event, [$ticketType->id => 2]);

    expect($order->items()->sum('quantity'))->toEqual(2);
});

test('rejects a ticket type outside its sales period', function (string $state) {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->{$state}()->create(['name' => '一般', 'capacity' => 50]);

    expect(fn () => app(OrderService::class)->createOrder(User::factory()->create(), $event, [$ticketType->id => 1]))
        ->toThrow(PurchaseException::class, '「一般」は販売期間外です。');

    expect(Order::count())->toBe(0);
})->with(['beforeSale', 'afterSale']);

test('rejects a quantity outside 1 to 10', function (mixed $quantity) {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['capacity' => 50]);

    expect(fn () => app(OrderService::class)->createOrder(User::factory()->create(), $event, [$ticketType->id => $quantity]))
        ->toThrow(PurchaseException::class, '枚数は1枚から10枚の間で選んでください。');

    expect(Order::count())->toBe(0);
})->with([
    'eleven' => [11],
    'zero' => [0],
    'negative' => [-1],
    'not a whole number' => ['1.5'],
    'not a number' => ['abc'],
    'an array' => [[1]],
]);

test('accepts the maximum quantity of 10', function () {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['price' => 1000, 'capacity' => 50]);

    $order = app(OrderService::class)->createOrder(User::factory()->create(), $event, [$ticketType->id => '10']);

    expect($order->total_amount)->toBe(10000);
});

test('creates nothing when one of several ticket types fails', function () {
    $event = Event::factory()->published()->create();
    $onSale = TicketType::factory()->for($event)->create(['capacity' => 50]);
    $ended = TicketType::factory()->for($event)->afterSale()->create(['capacity' => 50]);

    expect(fn () => app(OrderService::class)->createOrder(User::factory()->create(), $event, [$onSale->id => 1, $ended->id => 1]))
        ->toThrow(PurchaseException::class);

    expect(Order::count())->toBe(0)
        ->and(OrderItem::count())->toBe(0);
});

test('rejects a ticket type that belongs to another event', function () {
    $event = Event::factory()->published()->create();
    $otherTicketType = TicketType::factory()->for(Event::factory()->published())->create(['capacity' => 50]);

    expect(fn () => app(OrderService::class)->createOrder(User::factory()->create(), $event, [$otherTicketType->id => 1]))
        ->toThrow(PurchaseException::class, '選択した券種が見つかりません。');

    expect(Order::count())->toBe(0);
});

test('rejects an order with no ticket types', function () {
    $event = Event::factory()->published()->create();

    expect(fn () => app(OrderService::class)->createOrder(User::factory()->create(), $event, []))
        ->toThrow(PurchaseException::class, '券種と枚数を選んでください。');

    expect(Order::count())->toBe(0);
});

test('rejects an event that is not published', function (string $state) {
    $event = Event::factory()->{$state}()->create();
    $ticketType = TicketType::factory()->for($event)->create(['capacity' => 50]);

    expect(fn () => app(OrderService::class)->createOrder(User::factory()->create(), $event, [$ticketType->id => 1]))
        ->toThrow(PurchaseException::class, 'このイベントは現在購入できません。');

    expect(Order::count())->toBe(0);
})->with(['draft', 'unpublished']);
