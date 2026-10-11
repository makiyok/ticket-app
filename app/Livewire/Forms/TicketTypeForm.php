<?php

namespace App\Livewire\Forms;

use App\Models\TicketType;
use Livewire\Form;

class TicketTypeForm extends Form
{
    public string $name = '';

    /**
     * 数値の入力欄を空にすると空文字が届き、Livewire はそれを null に直す。
     * int だけ、または int|string だと、その時点で型エラーになるので null も許す。
     */
    public int|string|null $price = '';

    public int|string|null $capacity = '';

    public string $sales_starts_at = '';

    public string $sales_ends_at = '';

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'price' => ['required', 'integer', 'min:0', 'max:9999999'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100000'],
            'sales_starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'sales_ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:sales_starts_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => '券種名',
            'price' => '価格',
            'capacity' => '定員',
            'sales_starts_at' => '販売開始',
            'sales_ends_at' => '販売終了',
        ];
    }

    /**
     * 編集するときの初期値。日時は datetime-local 用の形式（Y-m-d\TH:i）にそろえる。
     */
    public function setTicketType(TicketType $ticketType): void
    {
        $this->name = $ticketType->name;
        $this->price = $ticketType->price;
        $this->capacity = $ticketType->capacity;
        $this->sales_starts_at = $ticketType->sales_starts_at->format('Y-m-d\TH:i');
        $this->sales_ends_at = $ticketType->sales_ends_at->format('Y-m-d\TH:i');
    }

    /**
     * 検証してから、保存に使う値を返す。価格と定員は整数にそろえる。
     *
     * @return array{name: string, price: int, capacity: int, sales_starts_at: string, sales_ends_at: string}
     */
    public function payload(): array
    {
        $validated = $this->validate();

        return [
            'name' => $validated['name'],
            'price' => (int) $validated['price'],
            'capacity' => (int) $validated['capacity'],
            'sales_starts_at' => $validated['sales_starts_at'],
            'sales_ends_at' => $validated['sales_ends_at'],
        ];
    }
}
