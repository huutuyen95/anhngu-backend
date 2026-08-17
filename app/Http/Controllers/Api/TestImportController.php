<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Test\CommitImportedTestRequest;
use App\Http\Requests\Test\ImportTestDryRunRequest;
use App\Http\Requests\Test\UploadSectionAudioRequest;
use App\Http\Resources\TestResource;
use App\Models\Test;
use App\Models\TestSection;
use App\Services\TestImportService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TestImportController extends Controller
{
    public function __construct(private readonly TestImportService $imports) {}

    public function template(): BinaryFileResponse
    {
        return response()->download($this->imports->templatePath(), 'mau-de-thi.docx')->deleteFileAfterSend(true);
    }

    public function dryRun(ImportTestDryRunRequest $request): JsonResponse
    {
        return response()->json($this->imports->parse($request->file('file')));
    }

    public function commit(CommitImportedTestRequest $request): JsonResponse
    {
        return response()->json(['test' => new TestResource($this->imports->commit($request->validated(), $request->user()))], 201);
    }

    public function sectionAudio(UploadSectionAudioRequest $request, Test $test, TestSection $section): JsonResponse
    {
        return response()->json(['audio_url' => $this->imports->uploadSectionAudio($section, $request->file('file'))]);
    }
}
