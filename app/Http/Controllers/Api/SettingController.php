<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\DeleteSettingFileRequest;
use App\Http\Requests\Setting\MailTestRequest;
use App\Http\Requests\Setting\ResetSettingsRequest;
use App\Http\Requests\Setting\UpdateSettingsRequest;
use App\Http\Requests\Setting\UploadSettingFileRequest;
use App\Http\Responses\ApiResponse;
use App\Models\SettingChange;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    public function __construct(private readonly SettingService $settings) {}

    public function index(): JsonResponse
    {
        return response()->json($this->settings->indexPayload());
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        return response()->json([
            'saved' => $this->settings->updateSettings($request->validated('values')),
        ]);
    }

    public function reset(ResetSettingsRequest $request): JsonResponse
    {
        $this->settings->resetSettings($request->validated());

        return ApiResponse::message('Đã khôi phục mặc định.');
    }

    public function upload(UploadSettingFileRequest $request): JsonResponse
    {
        return response()->json([
            'url' => $this->settings->uploadFile($request->file('file')),
        ]);
    }

    public function deleteFile(DeleteSettingFileRequest $request): JsonResponse
    {
        $this->settings->deleteFile($request->validated('key'));

        return ApiResponse::message('Đã xoá tệp.');
    }

    public function changes(): JsonResponse
    {
        return response()->json($this->settings->changesPayload());
    }

    public function revert(SettingChange $change): JsonResponse
    {
        $this->settings->revert($change);

        return ApiResponse::message('Đã hoàn tác thay đổi.');
    }

    public function mailTest(MailTestRequest $request): JsonResponse
    {
        $result = $this->settings->sendTestMail($request->validated());

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function publicBranding(): JsonResponse
    {
        return response()->json($this->settings->publicBranding());
    }
}
