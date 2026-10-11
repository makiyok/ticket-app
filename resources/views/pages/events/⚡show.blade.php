<?php

use App\Models\Event;
use App\Services\OrderService;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('イベント詳細')] class extends Component {
    public Event $event;

    /**
     * 券種の ID => 枚数。画面から書き換えられる値なので、注文を作るときに OrderService がもう一度確認する。
     *
     * @var array<array-key, mixed>
     */
    public array $quantities = [];

    public function mount(Event $event): void
    {
        $this->authorize('view', $event);

        $this->event = $event;
        $this->quantities = $this->ticketTypes->mapWithKeys(fn ($ticketType) => [$ticketType->id => 0])->all();
    }

    /**
     * 選んだ枚数をセッションに保存して、確認画面へ進む。未ログインならログインをはさむ。
     */
    public function proceed(): void
    {
        $this->authorize('view', $this->event);

        $this->resetErrorBag('quantities');

        $selected = [];

        foreach ($this->ticketTypes as $ticketType) {
            $quantity = $this->quantities[$ticketType->id] ?? 0;

            if ((is_int($quantity) || (is_string($quantity) && ctype_digit($quantity))) && (int) $quantity > 0) {
                $selected[$ticketType->id] = (int) $quantity;
            }
        }

        if ($selected === []) {
            $this->addError('quantities', '枚数を選んでください。');

            return;
        }

        session()->put("purchase.{$this->event->id}", $selected);

        $this->redirectRoute('events.purchase', $this->event);
    }

    /**
     * セレクトで選べる枚数の上限
     */
    public function maxQuantity(int $remaining): int
    {
        return min(OrderService::MAX_QUANTITY, $remaining);
    }

    /**
     * このイベントの券種（販売開始の早い順）
     *
     * @return Collection<int, \App\Models\TicketType>
     */
    #[Computed]
    public function ticketTypes(): Collection
    {
        return $this->event->ticketTypes()->orderBy('sales_starts_at')->orderBy('id')->get();
    }
}; ?>

<section class="w-full max-w-3xl space-y-6">
    <div class="flex items-center gap-3">
        <flux:heading size="xl" level="1">{{ $event->title }}</flux:heading>

        @unless ($event->isPublished())
            <flux:badge :color="$event->status->color()">{{ $event->status->label() }}</flux:badge>
        @endunless
    </div>

    <dl class="grid gap-4 sm:grid-cols-2">
        <div>
            <dt><flux:text>日時</flux:text></dt>
            <dd>
                {{ $event->starts_at->isoFormat('M月D日(ddd) HH:mm') }} 〜
                {{ $event->ends_at->isoFormat('M月D日(ddd) HH:mm') }}
            </dd>
        </div>

        <div>
            <dt><flux:text>会場</flux:text></dt>
            <dd>{{ $event->venue }}</dd>
        </div>
    </dl>

    <p class="whitespace-pre-line">{{ $event->description }}</p>

    <flux:separator />

    <div class="space-y-4">
        <flux:heading size="lg" level="2">チケット</flux:heading>

        @if ($this->ticketTypes->isEmpty())
            <flux:text>このイベントのチケットはまだ登録されていません。</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>券種名</flux:table.column>
                    <flux:table.column align="end">価格</flux:table.column>
                    <flux:table.column align="end">残り枚数</flux:table.column>
                    <flux:table.column>販売期間</flux:table.column>
                    <flux:table.column></flux:table.column>
                    <flux:table.column align="end">枚数</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->ticketTypes as $ticketType)
                        @php($remaining = $ticketType->remaining())

                        <flux:table.row :key="$ticketType->id">
                            <flux:table.cell>{{ $ticketType->name }}</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($ticketType->price) }}円</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($remaining) }}枚</flux:table.cell>
                            <flux:table.cell>
                                {{ $ticketType->sales_starts_at->isoFormat('M月D日(ddd) HH:mm') }} 〜<br>
                                {{ $ticketType->sales_ends_at->isoFormat('M月D日(ddd) HH:mm') }}
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                @if (now()->lt($ticketType->sales_starts_at))
                                    <flux:badge size="sm" color="zinc">販売前</flux:badge>
                                @elseif (! $ticketType->isOnSale())
                                    <flux:badge size="sm" color="zinc">販売終了</flux:badge>
                                @elseif ($remaining === 0)
                                    <flux:badge size="sm" color="red">売り切れ</flux:badge>
                                @else
                                    <flux:badge size="sm" color="green">販売中</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                @if ($ticketType->isOnSale() && $remaining > 0)
                                    <flux:select
                                        wire:model="quantities.{{ $ticketType->id }}"
                                        class="w-24"
                                        aria-label="{{ $ticketType->name }}の枚数"
                                        data-test="quantity-{{ $ticketType->id }}"
                                    >
                                        @foreach (range(0, $this->maxQuantity($remaining)) as $quantity)
                                            <flux:select.option value="{{ $quantity }}">{{ $quantity }}枚</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            @if ($event->isPublished())
            <div class="flex flex-col items-end gap-2">
                <flux:error name="quantities" />

                <flux:button variant="primary" wire:click="proceed" data-test="proceed-button">購入手続きへ進む</flux:button>

                @guest
                    <flux:text>購入にはログインが必要です。</flux:text>
                @endguest
            </div>
            @endif
        @endif
    </div>
</section>
