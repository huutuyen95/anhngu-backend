<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->auth->register($request->validated());

        return response()->json(['user' => new UserResource($result['user']), 'token' => $result['token']], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login((string) $request->string('email'), (string) $request->string('password'), $request->throttleKey());

        return response()->json(['user' => new UserResource($result['user']), 'token' => $result['token']]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());

        return response()->json(['message' => 'Đã đăng xuất.']);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->auth->forgotPassword((string) $request->string('email'));

        return response()->json(['message' => 'Nếu email tồn tại, chúng tôi đã gửi link đặt lại mật khẩu.']);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->auth->resetPassword($request->validated());

        return response()->json(['message' => 'Đặt lại mật khẩu thành công.']);
    }
}
