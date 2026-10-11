<?php

use App\Enums\EventStatus;
use App\Livewire\Forms\EventForm;
use App\Models\Event;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('イベント編集')] class extends Component {
    public Event $event;

    public EventForm $form;

    public function mount(Event $event): void
    {
        $this->authorize('update', $event);

        $this->event = $event;
        $this->form->setEvent($event);
    }

    public function save(): void
    {
        $this->authorize('update', $this->event);

        $this->event->update($this->form->validate());

        Flux::toast(variant: 'success', text: 'イベントを保存しました。');
    }

    /**
     * 下書きと公開中を切り替える。管理者が非公開にしたイベントは主催者には戻せない。
     */
    public function togglePublish(): void
    {
        $this->authorize('togglePublish', $this->event);

        $this->event->status = $this->event->isPublished() ? EventStatus::Draft : EventStatus::Published;
        $this->event->save();

        Flux::toast(
            variant: 'success',
            text: $this->event->isPublished() ? 'イベントを公開しました。' : 'イベントを下書きに戻しました。',
        );
    }

    public function deleteEvent(): void
    {
        $this->authorize('delete', $this->event);

        $this->event->delete();

        Flux::toast(variant: 'success', text: 'イベントを削除しました。');

        $this->redirectRoute('organizer.events.index', navigate: true);
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <flux:heading size="xl" level="1">イベント編集</flux:heading>
            <flux:badge :color="$event->status->color()">{{ $event->status->label() }}</flux:badge>
        </div>

        <flux:button variant="ghost" :href="route('organizer.events.index')" wire:navigate>一覧へ戻る</flux:button>
    </div>

    @if ($event->status === EventStatus::Unpublished)
        <flux:callout variant="warning" icon="exclamation-triangle" heading="このイベントは管理者によって非公開にされています。" />
    @endif

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="form.title" label="イベント名" type="text" required />

        <flux:textarea wire:model="form.description" label="説明" rows="6" required />

        <flux:input wire:model="form.venue" label="会場" type="text" required />

        <div class="grid gap-6 sm:grid-cols-2">
            <flux:input wire:model="form.starts_at" label="開始日時" type="datetime-local" required />
            <flux:input wire:model="form.ends_at" label="終了日時" type="datetime-local" required />
        </div>

        <div class="flex justify-end">
            <flux:button variant="primary" type="submit" data-test="save-event-button">保存</flux:button>
        </div>
    </form>

    <flux:separator />

    <div class="flex items-center justify-between">
        @can('togglePublish', $event)
            <flux:button wire:click="togglePublish" data-test="toggle-publish-button">
                {{ $event->isPublished() ? '下書きに戻す' : '公開する' }}
            </flux:button>
        @else
            <span></span>
        @endcan

        <flux:modal.trigger name="confirm-event-deletion">
            <flux:button variant="danger" data-test="delete-event-button">削除</flux:button>
        </flux:modal.trigger>
    </div>

    <flux:modal name="confirm-event-deletion" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">イベントを削除しますか？</flux:heading>
                <flux:text class="mt-2">「{{ $event->title }}」を削除します。この操作は取り消せません。</flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">キャンセル</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="deleteEvent" data-test="confirm-delete-event-button">削除する</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
