<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('マイチケット')] class extends Component {
    use WithPagination;

    /**
     * ログイン中のユーザー自身の注文（新しい順）
     */
    #[Computed]
    public function orders(): LengthAwarePaginator
    {
        return Auth::user()->orders()->with('event')->latest()->latest('id')->paginate(10);
    }
}; ?>

<section class="w-full space-y-6">
    <flux:heading size="xl" level="1">マイチケット</flux:heading>

    @if ($this->orders->isEmpty())
        <flux:text>まだ注文がありません。</flux:text>

        <flux:button :href="route('home')" wire:navigate>イベントを探す</flux:button>
    @else
        <flux:table :paginate="$this->orders">
            <flux:table.columns>
                <flux:table.column>イベント名</flux:table.column>
                <flux:table.column>開催日</flux:table.column>
                <flux:table.column>注文日</flux:table.column>
                <flux:table.column align="end">合計金額</flux:table.column>
                <flux:table.column>状態</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->orders as $order)
                    <flux:table.row :key="$order->id">
                        <flux:table.cell>{{ $order->event->title }}</flux:table.cell>
                        <flux:table.cell>{{ $order->event->starts_at->isoFormat('M月D日(ddd) HH:mm') }}</flux:table.cell>
                        <flux:table.cell>{{ $order->created_at->isoFormat('M月D日(ddd) HH:mm') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format($order->total_amount) }}円</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$order->status->color()">{{ $order->status->label() }}</flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>
