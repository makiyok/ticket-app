<?php

use App\Livewire\Forms\EventForm;
use App\Models\Event;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('イベント登録')] class extends Component {
    public EventForm $form;

    public function mount(): void
    {
        $this->authorize('create', Event::class);
    }

    /**
     * 下書きとしてイベントを保存し、編集画面へ進む。
     */
    public function save(): void
    {
        $this->authorize('create', Event::class);

        $event = Auth::user()->events()->create($this->form->validate());

        Flux::toast(variant: 'success', text: 'イベントを下書きで保存しました。');

        $this->redirectRoute('organizer.events.edit', $event, navigate: true);
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" level="1">イベント登録</flux:heading>

        <flux:button variant="ghost" :href="route('organizer.events.index')" wire:navigate>一覧へ戻る</flux:button>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="form.title" label="イベント名" type="text" required autofocus />

        <flux:textarea wire:model="form.description" label="説明" rows="6" required />

        <flux:input wire:model="form.venue" label="会場" type="text" required />

        <div class="grid gap-6 sm:grid-cols-2">
            <flux:input wire:model="form.starts_at" label="開始日時" type="datetime-local" required />
            <flux:input wire:model="form.ends_at" label="終了日時" type="datetime-local" required />
        </div>

        <div class="flex justify-end">
            <flux:button variant="primary" type="submit" data-test="save-event-button">下書きで保存</flux:button>
        </div>
    </form>
</section>
