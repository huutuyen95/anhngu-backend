<?php

namespace App\Services;

use App\Models\AttemptAnswer;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Repositories\AttemptRepository;

class TestAttemptService
{
    public function __construct(private readonly AttemptRepository $attempts, private readonly AttemptStartService $start, private readonly TestGradingService $grading) {}

    public function start($user, Test $test, ?int $missionId): TestAttempt
    {
        abort_unless($test->is_published, 404);
        $a = $this->start->start($user, $test, $missionId);
        $a->setRelation('test', $test);

        return $a;
    }

    public function saveAnswers(TestAttempt $a, array $answers): void
    {
        $this->attempts->saveAnswers($a, $answers);
    }

    public function submit(TestAttempt $a): array
    {
        $result = $this->grading->submit($a);
        $result['grading'] = $this->gradingConfig($a);
        $result['source'] = $a->source;
        $result['mission'] = $this->missionContext($a);

        return $result;
    }

    public function result(TestAttempt $a): array
    {
        $a = $this->attempts->loadResult($a);
        $test = $this->attempts->resultTest($a);

        return ['id' => $a->id, 'status' => $a->status, 'source' => $a->source, 'mission' => $this->missionContext($a), 'total_score' => (float) $a->total_score, 'correct_count' => $a->correct_count, 'question_count' => $a->question_count, 'started_at' => $a->started_at, 'submitted_at' => $a->submitted_at, 'grading' => $this->gradingConfig($a), 'test' => $test, 'answers' => $a->answers->map(fn (AttemptAnswer $x) => ['question_id' => $x->question_id, 'question_option_id' => $x->question_option_id, 'answer_text' => $x->answer_text, 'answer_file_url' => $x->answer_file_url, 'is_correct' => $x->is_correct, 'score' => (float) $x->score, 'feedback' => $x->feedback, 'graded_by' => $x->gradedBy?->name, 'graded_at' => $x->graded_at])->values()];
    }

    public function state(TestAttempt $a): array
    {
        $a = $this->attempts->loadState($a);

        return ['id' => $a->id, 'status' => $a->status, 'source' => $a->source, 'mission' => $this->missionContext($a), 'started_at' => $a->started_at] + $this->clockState($a) + ['tab_exit_count' => $a->tab_exit_count, 'tab_exit_limit' => (int) $a->configValue('exam.leave_limit', TestAttempt::TAB_EXIT_LIMIT), 'tab_exit_action' => $a->configValue('exam.leave_action', 'warn'), 'block_copy' => (bool) $a->configValue('exam.block_copy', true), 'autosubmit_on_timeout' => (bool) $a->configValue('exam.autosubmit_on_timeout', true), 'answers' => $a->answers->map(fn (AttemptAnswer $x) => ['question_id' => $x->question_id, 'question_option_id' => $x->question_option_id, 'answer_text' => $x->answer_text, 'answer_file_url' => $x->answer_file_url])->values()];
    }

    public function clockState(TestAttempt $a): array
    {
        return ['deadline' => $a->deadlineAt(), 'remaining_seconds' => $a->remainingSeconds(), 'clock_running' => $a->clockRunning()];
    }

    public function pause(TestAttempt $a): array
    {
        $this->attempts->pause($a);

        return $this->clockState($a);
    }

    public function resume(TestAttempt $a): array
    {
        $this->attempts->resume($a);

        return $this->clockState($a);
    }

    public function tabExit(TestAttempt $a): array
    {
        $limit = (int) $a->configValue('exam.leave_limit', TestAttempt::TAB_EXIT_LIMIT);
        $action = $a->configValue('exam.leave_action', 'warn');
        if ($a->status !== 'in_progress') {
            return ['tab_exit_count' => $a->tab_exit_count, 'tab_exit_limit' => $limit, 'tab_exit_action' => $action, 'auto_submitted' => true, 'status' => $a->status];
        }$a = $this->attempts->incrementTabExit($a);
        if ($action === 'autosubmit' && $a->tab_exit_count > $limit) {
            return ['tab_exit_count' => $a->tab_exit_count, 'tab_exit_limit' => $limit, 'tab_exit_action' => $action, 'auto_submitted' => true, 'reason' => 'tab_exit_exceeded', 'result' => $this->grading->submit($a)];
        }

        return ['tab_exit_count' => $a->tab_exit_count, 'tab_exit_limit' => $limit, 'tab_exit_action' => $action, 'auto_submitted' => false];
    }

    private function missionContext(TestAttempt $a): ?array
    {
        if (! $a->mission_id) {
            return null;
        }$m = $this->attempts->mission($a);
        if (! $m) {
            return null;
        }$allowed = max(1, (int) ($m->attempts_allowed ?? 1));

        return ['id' => $m->id, 'classroom_id' => $m->classroom_id, 'classroom_name' => $m->classroom?->name, 'session_title' => $m->classSession?->title, 'session_order' => $m->classSession?->order, 'due_date' => $m->due_date?->toDateString(), 'attempts_allowed' => $allowed, 'attempts_used' => $this->attempts->missionAttemptsUsed($m)];
    }

    private function gradingConfig(TestAttempt $a): array
    {
        return ['decimals' => (int) $a->configValue('grading.decimals', 1), 'pass_score' => (float) $a->configValue('grading.pass_score', 5.0)];
    }
}
