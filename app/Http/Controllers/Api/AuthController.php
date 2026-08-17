<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->auth->register($request->validated());

        return ApiResponse::resource(new UserResource($result['user']), 'user', 201, ['token' => $result['token']]);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login((string) $request->string('email'), (string) $request->string('password'), $request->throttleKey());

        return ApiResponse::resource(new UserResource($result['user']), 'user', additional: ['token' => $result['token']]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());

        return ApiResponse::message('Đã đăng xuất.');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->auth->forgotPassword((string) $request->string('email'));

        return ApiResponse::message('Nếu email tồn tại, chúng tôi đã gửi link đặt lại mật khẩu.');
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->auth->resetPassword($request->validated());

        return ApiResponse::message('Đặt lại mật khẩu thành công.');
    }
}
