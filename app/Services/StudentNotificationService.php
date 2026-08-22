<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\NotificationRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StudentNotificationService
{
    public function __construct(private readonly NotificationRepository $notifications) {}

    public function list(User $user, string $filter, int $perPage = 20): LengthAwarePaginator
    {
        return $this->notifications->paginate($user, $filter === 'unread', $perPage);
    }

    public function unreadCount(User $user): int
    {
        return $this->notifications->unreadCount($user);
    }

    public function markRead(User $user, string $id): bool
    {
        return $this->notifications->markRead($user, $id);
    }

    public function markAllRead(User $user): int
    {
        return $this->notifications->markAllRead($user);
    }
}
