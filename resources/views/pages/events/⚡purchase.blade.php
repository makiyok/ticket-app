<?php

use App\Exceptions\PurchaseException;
use App\Models\Event;
use App\Models\TicketType;
use App\Services\OrderService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('購入内容の確認')] class extends Component {
    public Event $event;

    public function mount(Event $event): void
    {
        $this->authorize('view', $event);

        $this->event = $event;

        if ($this->quantities() === []) {
            $this->redirectRoute('events.show', $event);
        }
    }

    /**
     * 選んだ券種ごとの行（枚数と小計）。金額は表示用で、注文の金額は OrderService が決める。
     *
     * @return Collection<int, array{ticketType: TicketType, quantity: int, subtotal: int}>
     */
    #[Computed]
    public function lines(): Collection
    {
        $quantities = $this->quantities();

        return $this->event->ticketTypes()
            ->whereKey(array_keys($quantities))
            ->orderBy('sales_starts_at')
            ->orderBy('id')
            ->get()
            ->map(fn (TicketType $ticketType): array => [
                'ticketType' => $ticketType,
                'quantity' => $quantities[$ticketType->id],
                'subtotal' => $ticketType->price * $quantities[$ticketType->id],
            ]);
    }

    /**
     * 注文を作る。Stripe はまだ無いので、できたら仮の完了画面へ進む。
     */
    public function checkout(OrderService $orderService): void
    {
        $this->authorize('view', $this->event);

        $this->resetErrorBag('purchase');

        try {
            $order = $orderService->createOrder(Auth::user(), $this->event, $this->quantities());
        } catch (PurchaseException $e) {
            $this->addError('purchase', $e->getMessage());

            return;
        }

        session()->forget("purchase.{$this->event->id}");

        $this->redirectRoute('orders.complete', $order);
    }

    /**
     * イベント詳細で選んだ枚数（券種の ID => 枚数）。画面のプロパティには持たず、毎回セッションから読む。
     *
     * @return array<int, int>
     */
    private function quantities(): array
    {
        $quantities = session("purchase.{$this->event->id}", []);

        return is_array($quantities) ? $quantities : [];
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl" level="1">購入内容の確認</flux:heading>
        <flux:text class="mt-2">
            {{ $event->title }}（{{ $event->starts_at->isoFormat('M月D日(ddd) HH:mm') }}）
        </flux:text>
    </div>

    @error('purchase')
        <flux:callout variant="danger" icon="exclamation-triangle" :heading="$message" data-test="purchase-error" />
    @enderror

    <flux:table>
        <flux:table.columns>
            <flux:table.column>券種名</flux:table.column>
            <flux:table.column align="end">価格</flux:table.column>
            <flux:table.column align="end">枚数</flux:table.column>
            <flux:table.column align="end">小計</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->lines as $line)
                <flux:table.row :key="$line['ticketType']->id">
                    <flux:table.cell>{{ $line['ticketType']->name }}</flux:table.cell>
                    <flux:table.cell align="end">{{ number_format($line['ticketType']->price) }}円</flux:table.cell>
                    <flux:table.cell align="end">{{ $line['quantity'] }}枚</flux:table.cell>
                    <flux:table.cell align="end">{{ number_format($line['subtotal']) }}円</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <div class="flex items-center justify-end gap-4">
        <flux:text>合計</flux:text>
        <flux:heading size="lg">{{ number_format($this->lines->sum('subtotal')) }}円</flux:heading>
    </div>

    <div class="flex justify-end gap-2">
        <flux:button variant="ghost" :href="route('events.show', $event)" wire:navigate>枚数を選び直す</flux:button>
        <flux:button variant="primary" wire:click="checkout" data-test="checkout-button">支払いへ進む</flux:button>
    </div>
</section>
