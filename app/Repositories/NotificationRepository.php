<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Thao tác DB cho thông báo (dùng hệ notifications chuẩn của Laravel). */
class NotificationRepository
{
    public function paginate(User $user, bool $unreadOnly, int $perPage): LengthAwarePaginator
    {
        $query = $user->notifications();
        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        return $query->paginate($perPage);
    }

    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function markRead(User $user, string $id): bool
    {
        $notification = $user->notifications()->whereKey($id)->first();
        if (! $notification) {
            return false;
        }
        $notification->markAsRead();

        return true;
    }

    public function markAllRead(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }
}
