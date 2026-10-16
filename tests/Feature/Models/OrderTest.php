<?php

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Database\QueryException;

test('order status labels are in Japanese', function () {
    expect(OrderStatus::Pending->label())->toBe('支払い待ち')
        ->and(OrderStatus::Paid->label())->toBe('支払い済み')
        ->and(OrderStatus::Cancelled->label())->toBe('キャンセル')
        ->and(OrderStatus::Refunded->label())->toBe('返金済み');
});

test('a new order is pending by default', function () {
    $order = User::factory()->create()->orders()->create([
        'event_id' => Event::factory()->published()->create()->id,
        'total_amount' => 6000,
    ]);

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->isPending())->toBeTrue()
        ->and($order->isPaid())->toBeFalse()
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($order->fresh()->total_amount)->toBe(6000)
        ->and($order->fresh()->paid_at)->toBeNull();
});

test('status and owner cannot be mass assigned', function () {
    $order = new Order(['status' => 'paid', 'user_id' => 99, 'paid_at' => now(), 'total_amount' => 100]);

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->user_id)->toBeNull()
        ->and($order->paid_at)->toBeNull();
});

test('an order belongs to its buyer and event and has items', function () {
    $buyer = User::factory()->create();
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['price' => 3000]);

    $order = Order::factory()->for($buyer)->for($event)->paid()->create(['total_amount' => 6000]);
    $item = OrderItem::factory()->for($order)->for($ticketType)->create(['quantity' => 2, 'unit_price' => 3000]);

    expect($order->user->is($buyer))->toBeTrue()
        ->and($order->event->is($event))->toBeTrue()
        ->and($order->items)->toHaveCount(1)
        ->and($order->isPaid())->toBeTrue()
        ->and($order->paid_at)->not->toBeNull()
        ->and($item->order->is($order))->toBeTrue()
        ->and($item->ticketType->is($ticketType))->toBeTrue()
        ->and($item->fresh()->quantity)->toBe(2)
        ->and($item->fresh()->unit_price)->toBe(3000)
        ->and($buyer->orders)->toHaveCount(1)
        ->and($event->orders)->toHaveCount(1)
        ->and($ticketType->orderItems)->toHaveCount(1);
});

test('the same ticket type cannot appear twice in one order', function () {
    $item = OrderItem::factory()->create();

    OrderItem::factory()->for($item->order)->for($item->ticketType)->create();
})->throws(QueryException::class);

test('deleting an order deletes its items', function () {
    $item = OrderItem::factory()->create();

    $item->order->delete();

    expect(OrderItem::count())->toBe(0);
});

test('records with orders cannot be deleted', function (string $target) {
    $item = OrderItem::factory()->create();

    $record = match ($target) {
        'buyer' => $item->order->user,
        'event' => $item->order->event,
        'sold ticket type' => $item->ticketType,
    };

    $record->delete();
})->with(['buyer', 'event', 'sold ticket type'])->throws(QueryException::class);
