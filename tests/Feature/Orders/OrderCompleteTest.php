<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\OrderService;
use Livewire\Livewire;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiConnectionException;

function pendingOrderWithSession(int $quantity = 2): Order
{
    $order = Order::factory()->create(['total_amount' => 6000, 'stripe_checkout_session_id' => 'cs_test_123']);
    OrderItem::factory()->for($order)->create(['quantity' => $quantity, 'unit_price' => 3000]);

    return $order;
}

test('the complete page confirms payment when Stripe says paid', function () {
    $order = pendingOrderWithSession(2);
    $this->mock(CheckoutService::class)
        ->shouldReceive('retrieveSession')
        ->with('cs_test_123')
        ->andReturn(Session::constructFrom(['id' => 'cs_test_123', 'payment_status' => 'paid', 'payment_intent' => 'pi_test_1']));

    $this->actingAs($order->user)
        ->get(route('orders.complete', ['order' => $order, 'session_id' => 'cs_test_123']))
        ->assertOk()
        ->assertSee('購入が完了しました');

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->fresh()->payment_intent_id)->toBe('pi_test_1')
        ->and(Ticket::count())->toBe(2);
});

test('the complete page shows checking while Stripe says unpaid and keeps polling', function () {
    $order = pendingOrderWithSession();
    $this->mock(CheckoutService::class)
        ->shouldReceive('retrieveSession')
        ->andReturn(Session::constructFrom(['id' => 'cs_test_123', 'payment_status' => 'unpaid']));

    $this->actingAs($order->user)
        ->get(route('orders.complete', ['order' => $order, 'session_id' => 'cs_test_123']))
        ->assertOk()
        ->assertSee('支払いを確認しています')
        ->assertSee('wire:poll', false);

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and(Ticket::count())->toBe(0);
});

test('the poll confirms the payment once Stripe reports paid', function () {
    $order = pendingOrderWithSession();
    $this->mock(CheckoutService::class)
        ->shouldReceive('retrieveSession')
        ->andReturn(
            Session::constructFrom(['id' => 'cs_test_123', 'payment_status' => 'unpaid']),
            Session::constructFrom(['id' => 'cs_test_123', 'payment_status' => 'paid', 'payment_intent' => 'pi_test_1']),
        );

    Livewire::actingAs($order->user)
        ->withQueryParams(['session_id' => 'cs_test_123'])
        ->test('pages::orders.complete', ['order' => $order])
        ->assertSee('支払いを確認しています')
        ->call('confirmPayment')
        ->assertSee('購入が完了しました');

    expect($order->fresh()->isPaid())->toBeTrue();
});

test('an expired Stripe session stops the polling and says it could not be confirmed', function () {
    $order = pendingOrderWithSession();
    $this->mock(CheckoutService::class)
        ->shouldReceive('retrieveSession')
        ->andReturn(Session::constructFrom(['id' => 'cs_test_123', 'status' => 'expired', 'payment_status' => 'unpaid']));

    Livewire::actingAs($order->user)
        ->withQueryParams(['session_id' => 'cs_test_123'])
        ->test('pages::orders.complete', ['order' => $order])
        ->assertSet('gaveUp', true)
        ->assertSee('支払いを確認できませんでした')
        ->assertDontSee('wire:poll', false);

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

test('the polling gives up after the attempt limit and stops calling Stripe', function () {
    $order = pendingOrderWithSession();
    $this->mock(CheckoutService::class)
        ->shouldReceive('retrieveSession')
        ->times(20)
        ->andReturn(Session::constructFrom(['id' => 'cs_test_123', 'status' => 'open', 'payment_status' => 'unpaid']));

    $component = Livewire::actingAs($order->user)
        ->withQueryParams(['session_id' => 'cs_test_123'])
        ->test('pages::orders.complete', ['order' => $order])
        ->assertSet('attempts', 1);

    foreach (range(1, 19) as $unused) {
        $component->call('confirmPayment');
    }

    $component->assertSet('attempts', 20)->assertSet('gaveUp', false);

    $component->call('confirmPayment')
        ->assertSet('gaveUp', true)
        ->assertSee('支払いを確認できませんでした')
        ->assertDontSee('wire:poll', false);
});

test('a cancelled order shows its status without polling or calling Stripe', function () {
    $order = Order::factory()->cancelled()->create(['stripe_checkout_session_id' => 'cs_test_123']);
    $this->mock(CheckoutService::class)->shouldNotReceive('retrieveSession');

    $this->actingAs($order->user)
        ->get(route('orders.complete', ['order' => $order, 'session_id' => 'cs_test_123']))
        ->assertOk()
        ->assertSee('キャンセル')
        ->assertDontSee('購入が完了しました')
        ->assertDontSee('wire:poll', false);

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

test('a mismatched session id never reaches Stripe and changes nothing', function () {
    $order = pendingOrderWithSession();
    $this->mock(CheckoutService::class)->shouldNotReceive('retrieveSession');

    $this->actingAs($order->user)
        ->get(route('orders.complete', ['order' => $order, 'session_id' => 'cs_test_other']))
        ->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

test('an already paid order shows completion without calling Stripe', function () {
    $order = Order::factory()->paid()->create(['stripe_checkout_session_id' => 'cs_test_123']);
    $this->mock(CheckoutService::class)->shouldNotReceive('retrieveSession');

    $this->actingAs($order->user)
        ->get(route('orders.complete', ['order' => $order, 'session_id' => 'cs_test_123']))
        ->assertSee('購入が完了しました');
});

test('a free pending order is confirmed on the complete page', function () {
    $order = Order::factory()->create(['total_amount' => 0]);
    OrderItem::factory()->for($order)->create(['quantity' => 3, 'unit_price' => 0]);
    $this->mock(CheckoutService::class)->shouldNotReceive('retrieveSession');

    $this->actingAs($order->user)
        ->get(route('orders.complete', $order))
        ->assertSee('購入が完了しました');

    expect($order->fresh()->isPaid())->toBeTrue()
        ->and(Ticket::count())->toBe(3);
});

test('the organizer can view the page but does not confirm the payment', function () {
    $order = pendingOrderWithSession();
    $this->mock(CheckoutService::class)->shouldNotReceive('retrieveSession');

    $this->actingAs($order->event->organizer)
        ->get(route('orders.complete', ['order' => $order, 'session_id' => 'cs_test_123']))
        ->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

test('other customers cannot open the complete page', function () {
    $order = Order::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('orders.complete', $order))
        ->assertForbidden();
});

test('guests are redirected to login', function () {
    $order = Order::factory()->create();

    $this->get(route('orders.complete', $order))->assertRedirect(route('login'));
});

test('a Stripe API failure keeps the order pending and the page still renders', function () {
    $order = pendingOrderWithSession();
    $this->mock(CheckoutService::class)
        ->shouldReceive('retrieveSession')
        ->andThrow(new ApiConnectionException('timeout'));

    $this->actingAs($order->user)
        ->get(route('orders.complete', ['order' => $order, 'session_id' => 'cs_test_123']))
        ->assertOk()
        ->assertSee('支払いを確認しています');

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and(Ticket::count())->toBe(0);
});

test('the poll does not call Stripe when the session id does not match', function () {
    $order = pendingOrderWithSession();
    $this->mock(CheckoutService::class)->shouldNotReceive('retrieveSession');

    Livewire::actingAs($order->user)
        ->withQueryParams(['session_id' => 'cs_test_other'])
        ->test('pages::orders.complete', ['order' => $order])
        ->call('confirmPayment');

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

test('a Stripe paid answer does not issue tickets twice when the webhook already paid', function () {
    $order = pendingOrderWithSession(2);
    app(OrderService::class)->markAsPaid($order, 'pi_test_1');
    $this->mock(CheckoutService::class)->shouldNotReceive('retrieveSession');

    $this->actingAs($order->user)
        ->get(route('orders.complete', ['order' => $order, 'session_id' => 'cs_test_123']))
        ->assertSee('購入が完了しました');

    expect(Ticket::count())->toBe(2);
});
