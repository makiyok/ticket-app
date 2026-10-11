<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('イベント管理')] class extends Component {
    use WithPagination;

    /**
     * ログイン中の主催者自身のイベント（開催日の新しい順）
     */
    #[Computed]
    public function events(): LengthAwarePaginator
    {
        return Auth::user()->events()->latest('starts_at')->paginate(10);
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" level="1">イベント管理</flux:heading>

        <flux:button variant="primary" icon="plus" :href="route('organizer.events.create')" wire:navigate>
            イベントを登録
        </flux:button>
    </div>

    @if ($this->events->isEmpty())
        <flux:text>まだイベントがありません。「イベントを登録」から作成できます。</flux:text>
    @else
        <flux:table :paginate="$this->events">
            <flux:table.columns>
                <flux:table.column>イベント名</flux:table.column>
                <flux:table.column>開催日</flux:table.column>
                <flux:table.column>状態</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->events as $event)
                    <flux:table.row :key="$event->id">
                        <flux:table.cell>{{ $event->title }}</flux:table.cell>
                        <flux:table.cell>{{ $event->starts_at->isoFormat('M月D日(ddd) HH:mm') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$event->status->color()">{{ $event->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" :href="route('organizer.events.edit', $event)" wire:navigate>
                                編集
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>
