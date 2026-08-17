<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\ProfileRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProfileService
{
    public function __construct(private readonly ProfileRepository $profiles) {}

    public function show(User $user): array
    {
        $classroom = $this->profiles->classroom($user);

        return [
            'id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone,
            'birthday' => $user->birthday?->toDateString(), 'gender' => $user->gender, 'address' => $user->address,
            'facebook_url' => $user->facebook_url, 'avatar_url' => $user->avatar_url,
            'password_changed_at' => $user->password_changed_at, 'role' => $user->role->value,
            'classroom' => $classroom ? ['id' => $classroom->id, 'name' => $classroom->name, 'teacher_name' => $classroom->teacher?->name, 'joined_at' => $classroom->pivot->created_at?->toDateString()] : null,
            'student_code' => 'HS'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
            'active_sessions_count' => $this->profiles->activeSessions($user),
        ];
    }

    public function update(User $user, array $data): array
    {
        return $this->show($this->profiles->update($user, $data));
    }

    public function uploadAvatar(User $user, UploadedFile $file): string
    {
        $this->deleteAvatarFile($user->avatar_url);
        $path = $file->store('avatars', 'public');
        $this->squareResize(Storage::disk('public')->path($path), 400);

        return $this->profiles->update($user, ['avatar_url' => asset('storage/'.$path)])->avatar_url;
    }

    public function deleteAvatar(User $user): void
    {
        $this->deleteAvatarFile($user->avatar_url);
        $this->profiles->update($user, ['avatar_url' => null]);
    }

    public function updatePassword(User $user, array $data): string
    {
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['Mật khẩu hiện tại chưa đúng']]);
        }
        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => ['Mật khẩu mới không được giống mật khẩu cũ']]);
        }
        $this->profiles->updatePassword($user, ['password' => Hash::make($data['password']), 'password_changed_at' => now()]);
        $this->profiles->revokeAllTokens($user);

        return $this->profiles->issueToken($user);
    }

    public function logoutOthers(User $user): int
    {
        return $this->profiles->revokeOtherTokens($user, $user->currentAccessToken()?->id);
    }

    private function deleteAvatarFile(?string $url): void
    {
        if (! $url) {
            return;
        }
        $path = Str::after($url, '/storage/');
        if ($path !== $url && Str::startsWith($path, 'avatars/')) {
            Storage::disk('public')->delete($path);
        }
    }

    private function squareResize(string $path, int $size): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            return;
        }
        try {
            $info = getimagesize($path);
            if (! $info) {
                return;
            }
            [$width, $height] = $info;
            $source = match ($info[2]) {
                IMAGETYPE_JPEG => imagecreatefromjpeg($path), IMAGETYPE_PNG => imagecreatefrompng($path), default => null
            };
            if (! $source) {
                return;
            }
            $side = min($width, $height);
            $target = imagecreatetruecolor($size, $size);
            imagecopyresampled($target, $source, 0, 0, (int) (($width - $side) / 2), (int) (($height - $side) / 2), $size, $size, $side, $side);
            imagejpeg($target, $path, 85);
            imagedestroy($source);
            imagedestroy($target);
        } catch (\Throwable) {
        }
    }
}
