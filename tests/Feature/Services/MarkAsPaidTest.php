<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Services\OrderService;

test('markAsPaid pays a pending order and issues one ticket per seat with unique codes', function () {
    $order = Order::factory()->create(['total_amount' => 9000]);
    $first = OrderItem::factory()->for($order)->create(['quantity' => 2]);
    $second = OrderItem::factory()->for($order)->create(['quantity' => 1]);

    $result = app(OrderService::class)->markAsPaid($order, 'pi_test_1');

    expect($result)->toBeTrue()
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->fresh()->paid_at)->not->toBeNull()
        ->and($order->fresh()->payment_intent_id)->toBe('pi_test_1')
        ->and($first->tickets()->count())->toBe(2)
        ->and($second->tickets()->count())->toBe(1)
        ->and(Ticket::pluck('code')->unique()->count())->toBe(3);
});

test('markAsPaid does nothing the second time', function () {
    $order = Order::factory()->create();
    OrderItem::factory()->for($order)->create(['quantity' => 2]);
    $service = app(OrderService::class);

    $service->markAsPaid($order);
    $again = $service->markAsPaid(Order::find($order->id));

    expect($again)->toBeFalse()
        ->and(Ticket::count())->toBe(2);
});

test('markAsPaid ignores a stale model when another request already paid the order', function () {
    $order = Order::factory()->create();
    OrderItem::factory()->for($order)->create(['quantity' => 2]);
    $stale = Order::find($order->id);
    app(OrderService::class)->markAsPaid($order);

    expect(app(OrderService::class)->markAsPaid($stale))->toBeFalse()
        ->and(Ticket::count())->toBe(2);
});

test('markAsPaid leaves cancelled and refunded orders alone', function (string $state) {
    $order = Order::factory()->{$state}()->create();
    OrderItem::factory()->for($order)->create(['quantity' => 2]);

    expect(app(OrderService::class)->markAsPaid($order))->toBeFalse()
        ->and(Ticket::count())->toBe(0);
})->with(['cancelled', 'refunded']);
