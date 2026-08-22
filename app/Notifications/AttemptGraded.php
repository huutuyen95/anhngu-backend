<?php

namespace App\Notifications;

class AttemptGraded extends StudentNotification
{
    public function __construct(
        public int $testId,
        public int $attemptId,
        public string $testTitle,
        public ?float $score = null,
        public ?int $classroomId = null,
        public ?string $actor = null,
    ) {}

    public function toArray(object $notifiable): array
    {
        $score = $this->score !== null ? ' · '.rtrim(rtrim(number_format($this->score, 1, '.', ''), '0'), '.').' điểm' : '';

        return [
            'kind' => 'graded',
            'title' => 'Bài của em đã được chấm',
            'body' => "{$this->testTitle}{$score}",
            'url' => "/library/tests/{$this->testId}/result/{$this->attemptId}",
            'classroom_id' => $this->classroomId,
            'actor_name' => $this->actor,
        ];
    }
}
