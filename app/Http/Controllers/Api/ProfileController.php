<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /** Hồ sơ đầy đủ của người đang đăng nhập. */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $class = $user->classes()->with('teacher:id,name')->first();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'birthday' => $user->birthday?->toDateString(),
            'gender' => $user->gender,
            'address' => $user->address,
            'facebook_url' => $user->facebook_url,
            'avatar_url' => $user->avatar_url,
            'password_changed_at' => $user->password_changed_at,
            'role' => $user->role->value,
            'classroom' => $class ? [
                'id' => $class->id,
                'name' => $class->name,
                'teacher_name' => $class->teacher?->name,
                'joined_at' => $class->pivot->created_at?->toDateString(),
            ] : null,
            'student_code' => 'HS'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
            'active_sessions_count' => $user->tokens()->count(),
        ]);
    }

    /** Cập nhật hồ sơ. BỎ QUA email + role dù client có gửi. */
    public function update(Request $request): JsonResponse
    {
        // Tự thêm https:// cho link Facebook nếu thiếu scheme (trước khi validate).
        if ($request->filled('facebook_url') && ! Str::startsWith($request->input('facebook_url'), ['http://', 'https://'])) {
            $request->merge(['facebook_url' => 'https://'.$request->input('facebook_url')]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'regex:/^0[\d\s]{9,}$/'],
            'birthday' => [
                'nullable', 'date', 'before:today',
                'after_or_equal:'.now()->subYears(100)->toDateString(),
            ],
            'gender' => ['nullable', Rule::in(['male', 'female', 'unspecified'])],
            'address' => ['nullable', 'string', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
        ], [
            'name.required' => 'Em nhập họ tên nhé',
            'name.max' => 'Họ tên tối đa 100 ký tự',
            'phone.regex' => 'Số điện thoại chưa đúng',
            'birthday.before' => 'Em kiểm tra lại ngày sinh nhé',
            'birthday.before_or_equal' => 'Em kiểm tra lại ngày sinh nhé',
            'birthday.after_or_equal' => 'Em kiểm tra lại ngày sinh nhé',
            'birthday.date' => 'Em kiểm tra lại ngày sinh nhé',
            'facebook_url.url' => 'Link chưa hợp lệ',
        ]);

        // safe()->only: KHÔNG bao giờ nhận email/role kể cả khi client gửi lên.
        $user = $request->user();
        $user->fill($validated);
        $user->save();

        return response()->json(['user' => $this->show($request)->getData(true)]);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png', 'max:2048'],
        ], [
            'avatar.max' => 'Ảnh nặng quá 2MB, em chọn ảnh khác nhé.',
            'avatar.mimes' => 'Chỉ nhận ảnh JPG hoặc PNG.',
            'avatar.image' => 'Chỉ nhận ảnh JPG hoặc PNG.',
        ]);

        $user = $request->user();
        $this->deleteAvatarFile($user->avatar_url);

        $path = $request->file('avatar')->store('avatars', 'public');
        $this->squareResize(Storage::disk('public')->path($path), 400);

        $user->avatar_url = asset('storage/'.$path);
        $user->save();

        return response()->json(['avatar_url' => $user->avatar_url]);
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->deleteAvatarFile($user->avatar_url);
        $user->avatar_url = null;
        $user->save();

        return response()->json(['avatar_url' => null]);
    }

    /** Đổi mật khẩu — giữ phiên bằng cách cấp token mới cho client thay. */
    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/', 'confirmed'],
        ], [
            'password.min' => 'Mật khẩu mới cần ít nhất 8 ký tự',
            'password.regex' => 'Mật khẩu cần có cả chữ và số',
            'password.confirmed' => 'Hai mật khẩu chưa giống nhau',
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['Mật khẩu hiện tại chưa đúng']]);
        }
        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => ['Mật khẩu mới không được giống mật khẩu cũ']]);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'password_changed_at' => now(),
        ])->save();

        // Thu hồi mọi token, cấp token mới → client thay, KHÔNG bắt đăng nhập lại.
        $user->tokens()->delete();
        $token = $user->issueRoleToken()->plainTextToken;

        return response()->json(['message' => 'Đã đổi mật khẩu.', 'token' => $token]);
    }

    /** Đăng xuất các thiết bị khác (giữ token hiện tại). */
    public function logoutOthers(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentId = $user->currentAccessToken()?->id;

        $revoked = $user->tokens()->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))->count();
        $user->tokens()->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))->delete();

        return response()->json(['revoked_count' => $revoked]);
    }

    // ── Nội bộ ────────────────────────────────────────────────────────────

    private function deleteAvatarFile(?string $url): void
    {
        if (! $url) {
            return;
        }
        $path = Str::after($url, '/storage/');
        if ($path && $path !== $url && Str::startsWith($path, 'avatars/')) {
            Storage::disk('public')->delete($path);
        }
    }

    /** Crop vuông + resize về size×size bằng GD (best-effort, lỗi thì giữ ảnh gốc). */
    private function squareResize(string $absolutePath, int $size): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            return;
        }
        try {
            $info = getimagesize($absolutePath);
            if (! $info) {
                return;
            }
            [$w, $h] = $info;
            $src = match ($info[2]) {
                IMAGETYPE_JPEG => imagecreatefromjpeg($absolutePath),
                IMAGETYPE_PNG => imagecreatefrompng($absolutePath),
                default => null,
            };
            if (! $src) {
                return;
            }
            $side = min($w, $h);
            $sx = (int) (($w - $side) / 2);
            $sy = (int) (($h - $side) / 2);
            $dst = imagecreatetruecolor($size, $size);
            imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $size, $size, $side, $side);
            imagejpeg($dst, $absolutePath, 85);
            imagedestroy($src);
            imagedestroy($dst);
        } catch (\Throwable) {
            // giữ nguyên ảnh gốc
        }
    }
}
