<?php

namespace App\Enums;

enum EventStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Unpublished = 'unpublished';

    /**
     * 画面に表示する日本語のラベル
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => '下書き',
            self::Published => '公開中',
            self::Unpublished => '非公開',
        };
    }

    /**
     * flux:badge に渡す色
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'zinc',
            self::Published => 'green',
            self::Unpublished => 'red',
        };
    }
}
