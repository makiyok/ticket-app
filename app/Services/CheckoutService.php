<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class CheckoutService
{
    public function __construct(private StripeClient $stripe) {}

    /**
     * Checkout Session を作り、決済ページの URL を返す。session.id は注文に保存する。
     *
     * @throws ApiErrorException
     */
    public function createSession(Order $order): string
    {
        $order->loadMissing('user', 'event', 'items.ticketType');

        $lineItems = $order->items->map(fn (OrderItem $item): array => [
            'quantity' => $item->quantity,
            'price_data' => [
                'currency' => 'jpy',
                'unit_amount' => $item->unit_price,
                'product_data' => [
                    'name' => "{$order->event->title} {$item->ticketType->name}",
                ],
            ],
        ])->all();

        $session = $this->stripe->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => $lineItems,
            'customer_email' => $order->user->email,
            'metadata' => ['order_id' => (string) $order->id],
            // {CHECKOUT_SESSION_ID} は Stripe が置き換える文字列なので、route() の後ろに足す
            'success_url' => route('orders.complete', $order).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('orders.cancel', $order),
            // Stripe の下限は作成から30分。通信の時間や時計のずれで下回らないよう1分足す
            'expires_at' => now()->addMinutes(31)->getTimestamp(),
        ]);

        $order->forceFill(['stripe_checkout_session_id' => $session->id])->save();

        return $session->url;
    }

    /**
     * @throws ApiErrorException
     */
    public function retrieveSession(string $id): Session
    {
        return $this->stripe->checkout->sessions->retrieve($id);
    }

    /**
     * 決済ページを失効させる。失効すると、そのページでは支払えなくなる。
     *
     * @throws ApiErrorException すでに支払い済み・失効済みのセッションなど
     */
    public function expireSession(string $id): void
    {
        $this->stripe->checkout->sessions->expire($id);
    }
}
