<?php

namespace App\Policies;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EventPolicy
{
    /**
     * 公開中なら誰でも。下書きと非公開は所有者と管理者だけで、それ以外には存在を隠す（404）。
     */
    public function view(?User $user, Event $event): Response
    {
        if ($event->isPublished()) {
            return Response::allow();
        }

        if ($user !== null && ($user->isAdmin() || $this->owns($user, $event))) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->isOrganizer();
    }

    public function update(User $user, Event $event): bool
    {
        return $this->owns($user, $event);
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->owns($user, $event);
    }

    /**
     * 下書きと公開中の切り替え。管理者が非公開にしたイベントは所有者にも戻せない。
     */
    public function togglePublish(User $user, Event $event): bool
    {
        return $this->owns($user, $event) && $event->status !== EventStatus::Unpublished;
    }

    /**
     * 問題のあるイベントを非公開にできるのは管理者だけ。
     */
    public function unpublish(User $user, Event $event): bool
    {
        return $user->isAdmin();
    }

    private function owns(User $user, Event $event): bool
    {
        return $user->id === $event->organizer_id;
    }
}
