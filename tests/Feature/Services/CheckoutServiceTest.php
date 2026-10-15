<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Services\CheckoutService;
use Stripe\Checkout\Session;
use Stripe\Service\Checkout\SessionService;
use Stripe\StripeClient;

test('createSession builds a jpy payment session and saves the session id', function () {
    $event = Event::factory()->published()->create(['title' => '朝焼けトレッキング']);
    $ticketType = TicketType::factory()->for($event)->create(['name' => '一般', 'price' => 3000]);
    $order = Order::factory()->for($event)->create(['total_amount' => 6000]);
    OrderItem::factory()->for($order)->for($ticketType)->create(['quantity' => 2, 'unit_price' => 3000]);

    $sessions = Mockery::mock(SessionService::class);
    $sessions->shouldReceive('create')
        ->once()
        ->withArgs(function (array $params) use ($order): bool {
            return $params['mode'] === 'payment'
                && $params['payment_method_types'] === ['card']
                && 30 * 60 <= $params['expires_at'] - time()
                && $params['line_items'] === [[
                    'quantity' => 2,
                    'price_data' => [
                        'currency' => 'jpy',
                        'unit_amount' => 3000,
                        'product_data' => ['name' => '朝焼けトレッキング 一般'],
                    ],
                ]]
                && $params['metadata']['order_id'] === (string) $order->id
                && $params['success_url'] === route('orders.complete', $order).'?session_id={CHECKOUT_SESSION_ID}'
                && $params['cancel_url'] === route('orders.cancel', $order);
        })
        ->andReturn(Session::constructFrom(['id' => 'cs_test_123', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_123']));

    $stripe = Mockery::mock(StripeClient::class);
    $stripe->shouldReceive('getService')->with('checkout')->andReturn((object) ['sessions' => $sessions]);

    $url = (new CheckoutService($stripe))->createSession($order);

    expect($url)->toBe('https://checkout.stripe.com/c/pay/cs_test_123')
        ->and($order->fresh()->stripe_checkout_session_id)->toBe('cs_test_123');
});

test('expireSession asks Stripe to expire the session', function () {
    $sessions = Mockery::mock(SessionService::class);
    $sessions->shouldReceive('expire')->once()->with('cs_test_123');
    $stripe = Mockery::mock(StripeClient::class);
    $stripe->shouldReceive('getService')->with('checkout')->andReturn((object) ['sessions' => $sessions]);

    (new CheckoutService($stripe))->expireSession('cs_test_123');
});
