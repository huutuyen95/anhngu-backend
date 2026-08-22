<?php

namespace App\Notifications;

class MissionAssigned extends StudentNotification
{
    public function __construct(
        public int $classroomId,
        public string $className,
        public int $count,
        public ?int $sessionId = null,
        public ?string $actor = null,
    ) {}

    public function toArray(object $notifiable): array
    {
        $q = $this->sessionId ? "?session={$this->sessionId}" : '';

        return [
            'kind' => 'mission',
            'title' => 'Cô vừa giao bài mới',
            'body' => "{$this->count} nội dung mới ở lớp {$this->className}",
            'url' => "/classes/{$this->classroomId}{$q}",
            'classroom_id' => $this->classroomId,
            'actor_name' => $this->actor,
        ];
    }
}
