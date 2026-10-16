<?php

use App\Models\Order;
use App\Services\CheckoutService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Stripe\Exception\ApiErrorException;

new #[Title('購入完了')] class extends Component {
    public Order $order;

    #[Url(as: 'session_id')]
    public ?string $sessionId = null;

    /**
     * 確認した回数。上限を超えたらポーリングをやめる（3秒おきで約1分）。
     */
    public int $attempts = 0;

    /**
     * これ以上確認しても支払いが確定しないと分かった（セッションの失効、または確認回数の上限）。
     */
    public bool $gaveUp = false;

    private const int MAX_ATTEMPTS = 20;

    public function mount(Order $order, OrderService $orderService, CheckoutService $checkoutService): void
    {
        $this->authorize('view', $order);

        $this->order = $order;

        $this->confirmPayment($orderService, $checkoutService);
    }

    /**
     * 支払いを確定する。Webhook が先に来ていれば何もしない。wire:poll からも呼ばれる。
     *
     * 確定できるのは注文者本人だけ。合計 0 円の注文は Stripe を通らないので、ここで確定する。
     * それ以外は、注文に保存した Checkout Session と一致する session_id のときだけ Stripe に問い合わせる。
     */
    public function confirmPayment(OrderService $orderService, CheckoutService $checkoutService): void
    {
        $this->order->refresh();

        if (! $this->order->isPending() || Auth::id() !== $this->order->user_id) {
            return;
        }

        if ($this->order->total_amount === 0) {
            $orderService->markAsPaid($this->order);

            return;
        }

        $expectedSessionId = $this->order->stripe_checkout_session_id;

        if ($expectedSessionId === null || $this->sessionId !== $expectedSessionId) {
            return;
        }

        if ($this->attempts >= self::MAX_ATTEMPTS) {
            $this->gaveUp = true;

            return;
        }

        $this->attempts++;

        try {
            $session = $checkoutService->retrieveSession($expectedSessionId);
        } catch (ApiErrorException $e) {
            report($e);

            return;
        }

        if ($session->payment_status === 'paid') {
            $paymentIntentId = is_string($session->payment_intent ?? null) ? $session->payment_intent : null;

            $orderService->markAsPaid($this->order, $paymentIntentId);

            return;
        }

        if (($session->status ?? null) === 'expired') {
            $this->gaveUp = true;
        }
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    @if ($order->isPaid())
        <flux:heading size="xl" level="1">購入が完了しました</flux:heading>

        <flux:text>
            注文番号: {{ $order->id }} ／ 合計: {{ number_format($order->total_amount) }}円
        </flux:text>

        <flux:button variant="primary" :href="route('orders.index')" wire:navigate>マイチケットを見る</flux:button>
    @elseif ($order->isPending() && $gaveUp)
        <flux:heading size="xl" level="1">支払いを確認できませんでした</flux:heading>

        <flux:text data-test="payment-gave-up">
            決済の結果をまだ確認できていません。支払いを済ませている場合は、しばらくしてからマイチケットを確認してください。
            注文番号: {{ $order->id }} ／ 合計: {{ number_format($order->total_amount) }}円
        </flux:text>

        <div class="flex gap-2">
            <flux:button variant="primary" :href="route('orders.index')" wire:navigate>マイチケットを見る</flux:button>
            <flux:button variant="ghost" :href="route('events.show', $order->event)" wire:navigate>イベントの詳細へ戻る</flux:button>
        </div>
    @elseif ($order->isPending() && $sessionId !== null)
        <div wire:poll.3s="confirmPayment" class="space-y-6" data-test="payment-pending">
            <flux:heading size="xl" level="1">支払いを確認しています</flux:heading>

            <flux:text>
                決済の結果を確認中です。このままお待ちください。
                注文番号: {{ $order->id }} ／ 合計: {{ number_format($order->total_amount) }}円
            </flux:text>
        </div>
    @else
        <flux:heading size="xl" level="1">注文の状態: {{ $order->status->label() }}</flux:heading>

        <flux:text>
            注文番号: {{ $order->id }} ／ 合計: {{ number_format($order->total_amount) }}円
        </flux:text>

        <flux:button :href="route('events.show', $order->event)" wire:navigate>イベントの詳細へ戻る</flux:button>
    @endif
</section>
