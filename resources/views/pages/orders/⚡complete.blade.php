<?php

use App\Models\Order;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('注文完了')] class extends Component {
    public Order $order;

    public function mount(Order $order): void
    {
        $this->authorize('view', $order);

        $this->order = $order;
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <flux:heading size="xl" level="1">注文を受け付けました</flux:heading>

    <flux:text>支払いは平日課題で実装します。</flux:text>

    <flux:text>
        注文番号: {{ $order->id }} ／ 合計: {{ number_format($order->total_amount) }}円 ／ {{ $order->status->label() }}
    </flux:text>

    <flux:button :href="route('events.show', $order->event)" wire:navigate>イベントの詳細へ戻る</flux:button>
</section>
