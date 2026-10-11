<?php

namespace App\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case Organizer = 'organizer';
    case Admin = 'admin';

    /**
     * 画面に表示する日本語のラベル
     */
    public function label(): string
    {
        return match ($this) {
            self::Customer => '参加者',
            self::Organizer => '主催者',
            self::Admin => '管理者',
        };
    }
}
