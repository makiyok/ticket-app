<?php

use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('イベント一覧')] class extends Component {
    use WithPagination;

    /**
     * 公開中かつ開催前のイベント（開催日が近い順）
     */
    #[Computed]
    public function events(): LengthAwarePaginator
    {
        return Event::query()->published()->upcoming()->oldest('starts_at')->oldest('id')->paginate(12);
    }
}; ?>

<section class="w-full space-y-6">
    <flux:heading size="xl" level="1">イベント一覧</flux:heading>

    @if ($this->events->isEmpty())
        <flux:text>現在、開催予定のイベントはありません。</flux:text>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->events as $event)
                <a
                    href="{{ route('events.show', $event) }}"
                    wire:navigate
                    wire:key="event-{{ $event->id }}"
                    class="block space-y-2 rounded-xl border border-zinc-200 p-4 hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500"
                >
                    <flux:heading size="lg" level="2">{{ $event->title }}</flux:heading>
                    <flux:text>{{ $event->starts_at->isoFormat('M月D日(ddd) HH:mm') }}</flux:text>
                    <flux:text>{{ $event->venue }}</flux:text>
                </a>
            @endforeach
        </div>

        <flux:pagination :paginator="$this->events" />
    @endif
</section>
