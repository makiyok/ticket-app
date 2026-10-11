<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait EventValidationRules
{
    /**
     * Get the validation rules used to validate events.
     *
     * 日時は datetime-local の形式に固定する（タイムゾーン付きの値や "tomorrow" を通さない）。
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function eventRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:5000'],
            'venue' => ['required', 'string', 'max:100'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
        ];
    }
}
