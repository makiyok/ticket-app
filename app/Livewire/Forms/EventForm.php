<?php

namespace App\Livewire\Forms;

use App\Concerns\EventValidationRules;
use App\Models\Event;
use Livewire\Form;

class EventForm extends Form
{
    use EventValidationRules;

    public string $title = '';

    public string $description = '';

    public string $venue = '';

    public string $starts_at = '';

    public string $ends_at = '';

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return $this->eventRules();
    }

    /**
     * 編集画面の初期値。datetime-local 用の形式（Y-m-d\TH:i）にそろえる。
     */
    public function setEvent(Event $event): void
    {
        $this->title = $event->title;
        $this->description = $event->description;
        $this->venue = $event->venue;
        $this->starts_at = $event->starts_at->format('Y-m-d\TH:i');
        $this->ends_at = $event->ends_at->format('Y-m-d\TH:i');
    }
}
