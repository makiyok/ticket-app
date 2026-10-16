<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    /**
     * Stripe からの通知を受ける。署名を検証し、checkout.session.completed で注文を支払い済みにする。
     */
    public function __invoke(Request $request, OrderService $orderService): Response
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                (string) config('services.stripe.webhook_secret'),
            );
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return response('Invalid signature', 400);
        }

        if ($event->type !== 'checkout.session.completed') {
            return response('OK');
        }

        $session = $event->data->object;
        $orderId = $session->metadata->order_id ?? null;

        if (! $session instanceof Session || ! is_string($orderId) || ! ctype_digit($orderId)) {
            return response('OK');
        }

        $order = Order::find((int) $orderId);

        if ($order === null || $session->payment_status !== 'paid') {
            return response('OK');
        }

        // metadata だけに頼らず、自分が作ったセッションと金額が一致する通知だけを受け付ける
        if ($session->id !== $order->stripe_checkout_session_id || (int) $session->amount_total !== $order->total_amount) {
            report("Stripe の通知が注文と一致しません（注文 {$order->id}、セッション {$session->id}）");

            return response('OK');
        }

        $paymentIntentId = is_string($session->payment_intent ?? null) ? $session->payment_intent : null;

        if (! $orderService->markAsPaid($order, $paymentIntentId) && ! $order->refresh()->isPaid()) {
            // キャンセル済みなどで確定できなかった。お金は取れているので、気づけるようログに残す
            report("Stripe の支払い済み通知を反映できませんでした（注文 {$order->id}、状態 {$order->status->value}）");
        }

        return response('OK');
    }
}
