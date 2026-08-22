<?php

namespace App\Notifications;

class DeadlineSoon extends StudentNotification
{
    public function __construct(
        public int $classroomId,
        public string $className,
        public string $contentTitle,
        public string $dueDate,
        public ?int $sessionId = null,
    ) {}

    public function toArray(object $notifiable): array
    {
        $q = $this->sessionId ? "?session={$this->sessionId}" : '';

        return [
            'kind' => 'deadline',
            'title' => 'Sắp đến hạn nộp',
            'body' => "{$this->contentTitle} · hạn {$this->dueDate} ({$this->className})",
            'url' => "/classes/{$this->classroomId}{$q}",
            'classroom_id' => $this->classroomId,
            'actor_name' => null,
        ];
    }
}
