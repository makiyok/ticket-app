<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * 注文者本人、そのイベントの主催者、管理者。
     */
    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->user_id
            || $user->id === $order->event->organizer_id
            || $user->isAdmin();
    }

    /**
     * 支払いの中断は注文者本人だけ。主催者や管理者は他人の注文を取り消せない。
     */
    public function cancel(User $user, Order $order): bool
    {
        return $user->id === $order->user_id;
    }
}
