<?php

namespace App\Notifications;

class SessionNote extends StudentNotification
{
    public function __construct(
        public int $classroomId,
        public string $className,
        public int $sessionId,
        public string $sessionTitle,
        public ?string $actor = null,
    ) {}

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'note',
            'title' => 'Cô có ghi chú mới cho buổi học',
            'body' => "{$this->sessionTitle} · lớp {$this->className}",
            'url' => "/classes/{$this->classroomId}?session={$this->sessionId}",
            'classroom_id' => $this->classroomId,
            'actor_name' => $this->actor,
        ];
    }
}
