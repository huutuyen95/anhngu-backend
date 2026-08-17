<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\AuthRepository;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    private const LOGIN_DECAY_SECONDS = 60;

    public function __construct(private readonly AuthRepository $users) {}

    public function register(array $data): array
    {
        $user = $this->users->createUser(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password']), 'role' => UserRole::Student]);

        return ['user' => $user, 'token' => $this->users->issueToken($user)];
    }

    public function login(string $email, string $password, string $key): array
    {
        if (RateLimiter::tooManyAttempts($key, (int) setting('security.max_login_attempts', 5))) {
            $seconds = RateLimiter::availableIn($key);
            throw new HttpResponseException(response()->json(['code' => 'too_many_attempts', 'message' => "Bạn đã thử quá nhiều lần. Vui lòng thử lại sau {$seconds} giây."], 429)->header('Retry-After', (string) $seconds));
        }
        $user = $this->users->findByEmail($email);
        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($key, self::LOGIN_DECAY_SECONDS);
            throw ValidationException::withMessages(['email' => ['Email hoặc mật khẩu không đúng.']])->status(401);
        }
        if (! $user->is_active) {
            throw new HttpResponseException(response()->json(['code' => 'account_locked', 'message' => 'Tài khoản đang tạm khoá, vui lòng liên hệ cô giáo.'], 403));
        }
        RateLimiter::clear($key);

        return ['user' => $user, 'token' => $this->users->issueToken($user)];
    }

    public function logout(User $user): void
    {
        $this->users->deleteCurrentToken($user);
    }

    public function forgotPassword(string $email): void
    {
        Password::sendResetLink(['email' => $email]);
    }

    public function resetPassword(array $data): void
    {
        $status = Password::reset($data, function (User $user, string $password) {
            $this->users->updatePassword($user, ['password' => Hash::make($password), 'remember_token' => Str::random(60)]);
        });
        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }
    }
}
