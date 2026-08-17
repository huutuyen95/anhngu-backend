<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attempt\OwnedAttemptRequest;
use App\Http\Requests\Attempt\SaveAttemptAnswersRequest;
use App\Http\Requests\Attempt\StartAttemptRequest;
use App\Http\Requests\Attempt\UploadAttemptAudioRequest;
use App\Http\Resources\TestDetailResource;
use App\Models\Question;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Services\AttemptAudioService;
use App\Services\TestAttemptService;

class TestAttemptController extends Controller
{
    public function __construct(private readonly TestAttemptService $attempts, private readonly AttemptAudioService $audio) {}

    public function start(StartAttemptRequest $request, Test $test)
    {
        $a = $this->attempts->start($request->user(), $test, $request->integer('mission_id') ?: null);

        return response()->json(['attempt_id' => $a->id, 'started_at' => $a->started_at, ...$this->attempts->clockState($a), 'mission_id' => $a->mission_id, 'source' => $a->source]);
    }

    public function saveAnswers(SaveAttemptAnswersRequest $request, TestAttempt $attempt)
    {
        $this->attempts->saveAnswers($attempt, $request->validated('answers'));

        return response()->json(['message' => 'Đã lưu bài làm.']);
    }

    public function uploadAudio(UploadAttemptAudioRequest $request, TestAttempt $attempt, Question $question)
    {
        $answer = $this->audio->upload($attempt, $question, $request->file('file'));

        return response()->json(['url' => $answer->answer_file_url]);
    }

    public function deleteAudio(OwnedAttemptRequest $request, TestAttempt $attempt, Question $question)
    {
        $this->audio->delete($attempt, $question);

        return response()->json(['message' => 'Đã xoá bản ghi âm.']);
    }

    public function submit(OwnedAttemptRequest $request, TestAttempt $attempt)
    {
        return response()->json($this->attempts->submit($attempt));
    }

    public function result(OwnedAttemptRequest $request, TestAttempt $attempt)
    {
        $r = $this->attempts->result($attempt);
        $r['test'] = new TestDetailResource($r['test'], revealAnswers: true);

        return response()->json($r);
    }

    public function show(OwnedAttemptRequest $request, TestAttempt $attempt)
    {
        return response()->json($this->attempts->state($attempt));
    }

    public function pauseClock(OwnedAttemptRequest $request, TestAttempt $attempt)
    {
        return response()->json($this->attempts->pause($attempt));
    }

    public function resumeClock(OwnedAttemptRequest $request, TestAttempt $attempt)
    {
        return response()->json($this->attempts->resume($attempt));
    }

    public function tabExit(OwnedAttemptRequest $request, TestAttempt $attempt)
    {
        return response()->json($this->attempts->tabExit($attempt));
    }
}
