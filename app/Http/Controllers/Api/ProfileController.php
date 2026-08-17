<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UploadAvatarRequest;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $profiles) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->profiles->show($request->user()));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        return response()->json(['user' => $this->profiles->update($request->user(), $request->validated())]);
    }

    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        return response()->json(['avatar_url' => $this->profiles->uploadAvatar($request->user(), $request->file('avatar'))]);
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        $this->profiles->deleteAvatar($request->user());

        return response()->json(['avatar_url' => null]);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        return response()->json(['message' => 'Đã đổi mật khẩu.', 'token' => $this->profiles->updatePassword($request->user(), $request->validated())]);
    }

    public function logoutOthers(Request $request): JsonResponse
    {
        return response()->json(['revoked_count' => $this->profiles->logoutOthers($request->user())]);
    }
}
