<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use Illuminate\Testing\TestResponse;

/**
 * 正しい署名を付けた Stripe Webhook を送る。
 *
 * @param  array<string, mixed>  $session
 */
function postStripeEvent(mixed $test, string $type, array $session, ?string $secret = null): TestResponse
{
    $payload = json_encode([
        'id' => 'evt_test_'.uniqid(),
        'object' => 'event',
        'type' => $type,
        'data' => ['object' => $session + ['object' => 'checkout.session']],
    ]);
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret ?? config('services.stripe.webhook_secret'));

    return $test->call('POST', route('stripe.webhook'), [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        'CONTENT_TYPE' => 'application/json',
    ], $payload);
}

function pendingOrderForWebhook(int $quantity = 3): Order
{
    $order = Order::factory()->create(['total_amount' => 9000, 'stripe_checkout_session_id' => 'cs_test_123']);
    OrderItem::factory()->for($order)->create(['quantity' => $quantity, 'unit_price' => 3000]);

    return $order;
}

test('a correctly signed completed event pays the order and issues the tickets', function () {
    $order = pendingOrderForWebhook(3);

    postStripeEvent($this, 'checkout.session.completed', [
        'id' => 'cs_test_123',
        'amount_total' => 9000,
        'payment_status' => 'paid',
        'payment_intent' => 'pi_test_1',
        'metadata' => ['order_id' => (string) $order->id],
    ])->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->fresh()->payment_intent_id)->toBe('pi_test_1')
        ->and(Ticket::count())->toBe(3);
});

test('a wrong signature gets 400 and changes nothing', function () {
    $order = pendingOrderForWebhook();

    postStripeEvent($this, 'checkout.session.completed', [
        'id' => 'cs_test_123',
        'payment_status' => 'paid',
        'metadata' => ['order_id' => (string) $order->id],
    ], 'whsec_wrong')->assertStatus(400);

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and(Ticket::count())->toBe(0);
});

test('the webhook is excluded from CSRF protection while other posts are not', function () {
    // テスト中は CSRF の検証が省かれるので、本番と同じ環境に見せて検証を有効にする
    $this->app['env'] = 'production';

    $this->post(route('stripe.webhook'), [])->assertStatus(400);
    $this->post(route('logout'), [])->assertStatus(419);
});

test('a missing signature gets 400', function () {
    $this->postJson(route('stripe.webhook'), ['type' => 'checkout.session.completed'])->assertStatus(400);
});

test('the same event twice does not issue the tickets twice', function () {
    $order = pendingOrderForWebhook(3);
    $session = [
        'id' => 'cs_test_123',
        'amount_total' => 9000,
        'payment_status' => 'paid',
        'payment_intent' => 'pi_test_1',
        'metadata' => ['order_id' => (string) $order->id],
    ];

    postStripeEvent($this, 'checkout.session.completed', $session)->assertOk();
    postStripeEvent($this, 'checkout.session.completed', $session)->assertOk();

    expect(Ticket::count())->toBe(3);
});

test('an unpaid session leaves the order pending', function () {
    $order = pendingOrderForWebhook();

    postStripeEvent($this, 'checkout.session.completed', [
        'id' => 'cs_test_123',
        'payment_status' => 'unpaid',
        'metadata' => ['order_id' => (string) $order->id],
    ])->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

test('other event types and unknown orders are acknowledged without changes', function () {
    $order = pendingOrderForWebhook();

    postStripeEvent($this, 'charge.refunded', ['id' => 'ch_1'])->assertOk();
    postStripeEvent($this, 'checkout.session.completed', [
        'id' => 'cs_x',
        'payment_status' => 'paid',
        'metadata' => ['order_id' => '999999'],
    ])->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and(Ticket::count())->toBe(0);
});

test('a payment for an already cancelled order is not applied', function () {
    $order = Order::factory()->cancelled()->create(['stripe_checkout_session_id' => 'cs_test_123']);
    OrderItem::factory()->for($order)->create(['quantity' => 2]);

    postStripeEvent($this, 'checkout.session.completed', [
        'id' => 'cs_test_123',
        'amount_total' => $order->total_amount,
        'payment_status' => 'paid',
        'metadata' => ['order_id' => (string) $order->id],
    ])->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and(Ticket::count())->toBe(0);
});

test('a notification for a different session is ignored', function () {
    $order = pendingOrderForWebhook();

    postStripeEvent($this, 'checkout.session.completed', [
        'id' => 'cs_test_other',
        'amount_total' => 9000,
        'payment_status' => 'paid',
        'metadata' => ['order_id' => (string) $order->id],
    ])->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and(Ticket::count())->toBe(0);
});

test('a notification with a different amount is ignored', function () {
    $order = pendingOrderForWebhook();

    postStripeEvent($this, 'checkout.session.completed', [
        'id' => 'cs_test_123',
        'amount_total' => 100,
        'payment_status' => 'paid',
        'metadata' => ['order_id' => (string) $order->id],
    ])->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and(Ticket::count())->toBe(0);
});

/**
 * 支払い済みの checkout.session.completed の生ボディを作る。
 */
function paidSessionPayload(Order $order): string
{
    return json_encode([
        'id' => 'evt_test_'.uniqid(),
        'object' => 'event',
        'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'object' => 'checkout.session',
            'id' => 'cs_test_123',
            'amount_total' => $order->total_amount,
            'payment_status' => 'paid',
            'payment_intent' => 'pi_test_1',
            'metadata' => ['order_id' => (string) $order->id],
        ]],
    ]);
}

/**
 * 生ボディと Stripe-Signature ヘッダー（任意）をそのまま送る。
 */
function postRawStripe(mixed $test, string $body, ?string $signatureHeader): TestResponse
{
    $server = ['CONTENT_TYPE' => 'application/json'];

    if ($signatureHeader !== null) {
        $server['HTTP_STRIPE_SIGNATURE'] = $signatureHeader;
    }

    return $test->call('POST', route('stripe.webhook'), [], [], [], $server, $body);
}

function stripeSignatureHeader(string $signedPayload, int $timestamp, ?string $secret = null): string
{
    $hash = hash_hmac('sha256', "{$timestamp}.{$signedPayload}", $secret ?? config('services.stripe.webhook_secret'));

    return "t={$timestamp},v1={$hash}";
}

test('a body tampered after signing gets 400 and changes nothing', function () {
    $order = pendingOrderForWebhook(3);
    $signed = paidSessionPayload($order);
    $header = stripeSignatureHeader($signed, time());
    $tampered = str_replace('pi_test_1', 'pi_evil', $signed);

    postRawStripe($this, $tampered, $header)->assertStatus(400);

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($order->fresh()->payment_intent_id)->toBeNull()
        ->and(Ticket::count())->toBe(0);
});

test('a signature with a timestamp older than the tolerance gets 400 and changes nothing', function () {
    $order = pendingOrderForWebhook(3);
    $body = paidSessionPayload($order);
    $header = stripeSignatureHeader($body, time() - 3600);

    postRawStripe($this, $body, $header)->assertStatus(400);

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and(Ticket::count())->toBe(0);
});

test('a valid payload without a signature header gets 400 and changes nothing', function () {
    $order = pendingOrderForWebhook(3);

    postRawStripe($this, paidSessionPayload($order), null)->assertStatus(400);

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and(Ticket::count())->toBe(0);
});

test('a malformed signature header gets 400', function (string $header) {
    $order = pendingOrderForWebhook();

    postRawStripe($this, paidSessionPayload($order), $header)->assertStatus(400);

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
})->with(['garbage', 't=abc,v1=', 'v1=deadbeef']);

test('a correctly signed body that is not valid JSON gets 400', function () {
    $body = 'not-json';

    postRawStripe($this, $body, stripeSignatureHeader($body, time()))->assertStatus(400);
});

test('the webhook issues one ticket per seat across several items', function () {
    $order = Order::factory()->create(['stripe_checkout_session_id' => 'cs_test_123']);
    $first = OrderItem::factory()->for($order)->create(['quantity' => 2]);
    $second = OrderItem::factory()->for($order)->create(['quantity' => 4]);
    $body = paidSessionPayload($order);

    postRawStripe($this, $body, stripeSignatureHeader($body, time()))->assertOk();
    postRawStripe($this, $body, stripeSignatureHeader($body, time()))->assertOk();

    expect($first->tickets()->count())->toBe(2)
        ->and($second->tickets()->count())->toBe(4)
        ->and(Ticket::count())->toBe(6);
});
