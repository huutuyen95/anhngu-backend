<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Số lần đăng nhập sai tối đa trước khi khoá tạm — đọc từ cấu hình (mặc định 5 / 60 giây). */
    private function maxLoginAttempts(): int
    {
        return (int) setting('security.max_login_attempts', 5);
    }

    private const LOGIN_DECAY_SECONDS = 60;

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:'.(int) setting('security.password_min', 8), 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => UserRole::Student,
        ]);

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->issueRoleToken()->plainTextToken,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $key = $request->throttleKey();

        if (RateLimiter::tooManyAttempts($key, $this->maxLoginAttempts())) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'code' => 'too_many_attempts',
                'message' => "Bạn đã thử quá nhiều lần. Vui lòng thử lại sau {$seconds} giây.",
            ], 429)->header('Retry-After', (string) $seconds);
        }

        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            RateLimiter::hit($key, self::LOGIN_DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => ['Email hoặc mật khẩu không đúng.'],
            ])->status(401);
        }

        if (! $user->is_active) {
            return response()->json([
                'code' => 'account_locked',
                'message' => 'Tài khoản đang tạm khoá, vui lòng liên hệ cô giáo.',
            ], 403);
        }

        RateLimiter::clear($key);

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->issueRoleToken()->plainTextToken,
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Đã đăng xuất.']);
    }

    /**
     * Gửi link đặt lại mật khẩu. LUÔN trả 200 — không tiết lộ email có tồn tại hay không.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => 'Nếu email tồn tại, chúng tôi đã gửi link đặt lại mật khẩu.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['message' => 'Đặt lại mật khẩu thành công.']);
    }
}
