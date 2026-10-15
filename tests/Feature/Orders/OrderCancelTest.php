<?php

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Services\CheckoutService;
use Livewire\Livewire;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\InvalidRequestException;

test('opening the cancel page changes nothing', function () {
    $order = Order::factory()->create(['stripe_checkout_session_id' => 'cs_test_123']);
    $this->mock(CheckoutService::class)->shouldNotReceive('expireSession');

    $this->actingAs($order->user)
        ->get(route('orders.cancel', $order))
        ->assertOk()
        ->assertSee('支払いを中断しました')
        ->assertSee('注文を取り消す');

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

test('cancelling expires the Stripe session, cancels the order and frees the held tickets', function () {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['capacity' => 5]);
    $order = Order::factory()->for($event)->create(['stripe_checkout_session_id' => 'cs_test_123']);
    OrderItem::factory()->for($order)->for($ticketType)->create(['quantity' => 3]);
    expect($ticketType->remaining())->toBe(2);
    $this->mock(CheckoutService::class)->shouldReceive('expireSession')->once()->with('cs_test_123');

    Livewire::actingAs($order->user)
        ->test('pages::orders.cancel', ['order' => $order])
        ->call('cancelOrder')
        ->assertSee('キャンセルしました');

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($ticketType->remaining())->toBe(5);
});

test('an order without a Stripe session is cancelled without calling Stripe', function () {
    $order = Order::factory()->create(['stripe_checkout_session_id' => null]);
    $this->mock(CheckoutService::class)->shouldNotReceive('expireSession');

    Livewire::actingAs($order->user)
        ->test('pages::orders.cancel', ['order' => $order])
        ->call('cancelOrder');

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

test('if the session cannot be expired because it was already paid, the order is confirmed instead', function () {
    $order = Order::factory()->create(['total_amount' => 3000, 'stripe_checkout_session_id' => 'cs_test_123']);
    OrderItem::factory()->for($order)->create(['quantity' => 2, 'unit_price' => 1500]);
    $checkout = $this->mock(CheckoutService::class);
    $checkout->shouldReceive('expireSession')->andThrow(new InvalidRequestException('already complete'));
    $checkout->shouldReceive('retrieveSession')
        ->andReturn(Session::constructFrom(['id' => 'cs_test_123', 'status' => 'complete', 'payment_status' => 'paid', 'payment_intent' => 'pi_test_1']));

    Livewire::actingAs($order->user)
        ->test('pages::orders.cancel', ['order' => $order])
        ->call('cancelOrder')
        ->assertRedirect(route('orders.complete', $order));

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and(Ticket::count())->toBe(2);
});

test('an already expired session lets the order be cancelled', function () {
    $order = Order::factory()->create(['stripe_checkout_session_id' => 'cs_test_123']);
    $checkout = $this->mock(CheckoutService::class);
    $checkout->shouldReceive('expireSession')->andThrow(new InvalidRequestException('already expired'));
    $checkout->shouldReceive('retrieveSession')
        ->andReturn(Session::constructFrom(['id' => 'cs_test_123', 'status' => 'expired', 'payment_status' => 'unpaid']));

    Livewire::actingAs($order->user)
        ->test('pages::orders.cancel', ['order' => $order])
        ->call('cancelOrder');

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

test('if Stripe cannot be reached the order is not cancelled and an error is shown', function () {
    $order = Order::factory()->create(['stripe_checkout_session_id' => 'cs_test_123']);
    $checkout = $this->mock(CheckoutService::class);
    $checkout->shouldReceive('expireSession')->andThrow(new ApiConnectionException('network down'));
    $checkout->shouldReceive('retrieveSession')->andThrow(new ApiConnectionException('network down'));

    Livewire::actingAs($order->user)
        ->test('pages::orders.cancel', ['order' => $order])
        ->call('cancelOrder')
        ->assertSee('取り消せませんでした');

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

test('a paid order is not changed by cancelling', function () {
    $order = Order::factory()->paid()->create(['stripe_checkout_session_id' => 'cs_test_123']);
    $this->mock(CheckoutService::class)->shouldNotReceive('expireSession');

    Livewire::actingAs($order->user)
        ->test('pages::orders.cancel', ['order' => $order])
        ->assertSee('すでに支払い済み')
        ->call('cancelOrder');

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('only the buyer can open or cancel the order', function () {
    $order = Order::factory()->create();
    $this->mock(CheckoutService::class)->shouldNotReceive('expireSession');

    $this->actingAs(User::factory()->create())
        ->get(route('orders.cancel', $order))
        ->assertForbidden();

    $this->actingAs($order->event->organizer)
        ->get(route('orders.cancel', $order))
        ->assertForbidden();

    Livewire::actingAs($order->event->organizer)
        ->test('pages::orders.cancel', ['order' => $order])
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

test('guests are redirected to login', function () {
    $order = Order::factory()->create();

    $this->get(route('orders.cancel', $order))->assertRedirect(route('login'));
});
