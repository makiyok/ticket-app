<?php

use App\Models\Order;
use App\Services\CheckoutService;
use App\Services\OrderService;
use Livewire\Attributes\Title;
use Livewire\Component;
use Stripe\Exception\ApiErrorException;

new #[Title('支払いを中断しました')] class extends Component {
    public Order $order;

    public function mount(Order $order): void
    {
        $this->authorize('cancel', $order);

        $this->order = $order;
    }

    /**
     * 注文を取り消す。先に Stripe の決済ページを失効させ、取り消した後に支払われないようにする。
     *
     * 失効できなかったときは Stripe に状態を聞く。支払い済みなら確定し、失効済みなら取り消す。
     * どちらとも分からないときは、お金の取りこぼしを避けるため取り消さない。
     */
    public function cancelOrder(OrderService $orderService, CheckoutService $checkoutService): void
    {
        $this->authorize('cancel', $this->order);

        $this->resetErrorBag('cancel');
        $this->order->refresh();

        if (! $this->order->isPending()) {
            return;
        }

        $sessionId = $this->order->stripe_checkout_session_id;

        if ($sessionId !== null) {
            try {
                $checkoutService->expireSession($sessionId);
            } catch (ApiErrorException $e) {
                try {
                    $session = $checkoutService->retrieveSession($sessionId);
                } catch (ApiErrorException $retrieveError) {
                    report($retrieveError);

                    $this->addError('cancel', '取り消せませんでした。時間をおいてもう一度お試しください。');

                    return;
                }

                if ($session->payment_status === 'paid') {
                    $paymentIntentId = is_string($session->payment_intent ?? null) ? $session->payment_intent : null;
                    $orderService->markAsPaid($this->order, $paymentIntentId);

                    $this->redirectRoute('orders.complete', $this->order);

                    return;
                }

                if (($session->status ?? null) !== 'expired') {
                    report($e);

                    $this->addError('cancel', '取り消せませんでした。時間をおいてもう一度お試しください。');

                    return;
                }
            }
        }

        $this->order->cancelIfPending();
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <flux:heading size="xl" level="1">支払いを中断しました</flux:heading>

    @error('cancel')
        <flux:callout variant="danger" icon="exclamation-triangle" :heading="$message" data-test="cancel-error" />
    @enderror

    @if ($order->isPending())
        <flux:text>
            注文 {{ $order->id }} はまだ支払われていません。取り消すと、押さえていた枠が戻ります。
        </flux:text>

        <div class="flex gap-2">
            <flux:button variant="danger" wire:click="cancelOrder" wire:confirm="この注文を取り消しますか？" data-test="cancel-order-button">注文を取り消す</flux:button>
            <flux:button variant="ghost" :href="route('events.show', $order->event)" wire:navigate>イベントの詳細へ戻る</flux:button>
        </div>
    @else
        <flux:text>
            @if ($order->isPaid())
                この注文はすでに支払い済みです。
            @else
                注文 {{ $order->id }} をキャンセルしました。チケットは購入されていません。
            @endif
        </flux:text>

        <flux:button :href="route('events.show', $order->event)" wire:navigate>イベントの詳細へ戻る</flux:button>
    @endif
</section>
