<?php

use App\Enums\EventStatus;
use App\Livewire\Forms\EventForm;
use App\Livewire\Forms\TicketTypeForm;
use App\Models\Event;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('イベント編集')] class extends Component {
    public Event $event;

    public EventForm $form;

    public TicketTypeForm $ticketTypeForm;

    /**
     * 編集中の券種。null のときはフォームが「追加」になる。
     */
    #[Locked]
    public ?int $editingTicketTypeId = null;

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

        $this->resetErrorBag('publish');

        if (! $this->event->isPublished() && $this->event->ticketTypes()->doesntExist()) {
            $this->addError('publish', '券種を1つ以上登録してから公開してください。');

            return;
        }

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

    /**
     * 一覧の「編集」。券種は必ずこのイベントの中から探す（他のイベントの券種 ID は 404）。
     */
    public function editTicketType(int $id): void
    {
        $this->authorize('update', $this->event);

        $ticketType = $this->event->ticketTypes()->findOrFail($id);

        $this->ticketTypeForm->resetValidation();
        $this->ticketTypeForm->setTicketType($ticketType);
        $this->editingTicketTypeId = $ticketType->id;
    }

    /**
     * 追加と編集で共用する保存。編集中の券種があれば更新、無ければ追加する。
     */
    public function saveTicketType(): void
    {
        $this->authorize('update', $this->event);

        $payload = $this->ticketTypeForm->payload();

        if ($this->editingTicketTypeId === null) {
            $this->event->ticketTypes()->create($payload);
            $message = '券種を追加しました。';
        } else {
            $this->event->ticketTypes()->findOrFail($this->editingTicketTypeId)->update($payload);
            $message = '券種を更新しました。';
        }

        $this->resetTicketTypeForm();
        $this->resetErrorBag('publish');
        unset($this->ticketTypes);

        Flux::toast(variant: 'success', text: $message);
    }

    public function cancelTicketTypeEdit(): void
    {
        $this->authorize('update', $this->event);

        $this->resetTicketTypeForm();
    }

    /**
     * 券種を0件にできるのは下書きだけ。公開中と非公開では、最後の1件の削除を断る。
     */
    public function deleteTicketType(int $id): void
    {
        $this->authorize('update', $this->event);

        $ticketType = $this->event->ticketTypes()->findOrFail($id);

        $this->resetErrorBag('ticketTypes');

        if ($this->event->status !== EventStatus::Draft && $this->event->ticketTypes()->count() === 1) {
            $this->addError('ticketTypes', $this->event->isPublished()
                ? '公開中のイベントの最後の券種は削除できません。先に下書きに戻してください。'
                : '非公開のイベントの最後の券種は削除できません。');

            return;
        }

        $ticketType->delete();

        if ($this->editingTicketTypeId === $id) {
            $this->resetTicketTypeForm();
        }

        unset($this->ticketTypes);

        Flux::toast(variant: 'success', text: '券種を削除しました。');
    }

    private function resetTicketTypeForm(): void
    {
        $this->ticketTypeForm->reset();
        $this->ticketTypeForm->resetValidation();
        $this->editingTicketTypeId = null;
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

    <div class="space-y-4">
        <flux:heading size="lg" level="2">券種</flux:heading>

        @if ($this->ticketTypes->isEmpty())
            <flux:text>まだ券種がありません。下のフォームから追加してください。</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>券種名</flux:table.column>
                    <flux:table.column align="end">価格</flux:table.column>
                    <flux:table.column align="end">定員</flux:table.column>
                    <flux:table.column>販売期間</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->ticketTypes as $ticketType)
                        <flux:table.row :key="$ticketType->id">
                            <flux:table.cell>{{ $ticketType->name }}</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($ticketType->price) }}円</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($ticketType->capacity) }}枚</flux:table.cell>
                            <flux:table.cell>
                                {{ $ticketType->sales_starts_at->isoFormat('M月D日(ddd) HH:mm') }} 〜<br>
                                {{ $ticketType->sales_ends_at->isoFormat('M月D日(ddd) HH:mm') }}
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <div class="flex justify-end gap-2">
                                    <flux:button size="sm" wire:click="editTicketType({{ $ticketType->id }})">編集</flux:button>
                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        wire:click="deleteTicketType({{ $ticketType->id }})"
                                        wire:confirm="「{{ $ticketType->name }}」を削除しますか？"
                                    >
                                        削除
                                    </flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif

        <flux:error name="ticketTypes" />

        <form wire:submit="saveTicketType" class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading level="3">{{ $editingTicketTypeId === null ? '券種を追加' : '券種を編集' }}</flux:heading>

            <flux:input wire:model="ticketTypeForm.name" label="券種名" type="text" placeholder="一般" required />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="ticketTypeForm.price" label="価格（円）" type="number" min="0" step="1" inputmode="numeric" required />
                <flux:input wire:model="ticketTypeForm.capacity" label="定員（枚）" type="number" min="1" step="1" inputmode="numeric" required />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="ticketTypeForm.sales_starts_at" label="販売開始" type="datetime-local" required />
                <flux:input wire:model="ticketTypeForm.sales_ends_at" label="販売終了" type="datetime-local" required />
            </div>

            <div class="flex justify-end gap-2">
                @if ($editingTicketTypeId !== null)
                    <flux:button variant="ghost" wire:click="cancelTicketTypeEdit">キャンセル</flux:button>
                @endif

                <flux:button variant="primary" type="submit" data-test="save-ticket-type-button">
                    {{ $editingTicketTypeId === null ? '追加' : '更新' }}
                </flux:button>
            </div>
        </form>
    </div>

    <flux:separator />

    <div class="flex items-center justify-between">
        @can('togglePublish', $event)
            <div>
                <flux:button wire:click="togglePublish" data-test="toggle-publish-button">
                    {{ $event->isPublished() ? '下書きに戻す' : '公開する' }}
                </flux:button>

                @if (! $event->isPublished() && $this->ticketTypes->isEmpty())
                    <flux:text class="mt-2">公開するには券種が1つ以上必要です。</flux:text>
                @endif

                <flux:error name="publish" />
            </div>
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
