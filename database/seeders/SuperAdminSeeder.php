<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Cấp quyền super admin — tài khoản DUY NHẤT vào được Cài đặt hệ thống
 * (middleware `superadmin`, xem routes/api.php).
 *
 * Trước đây không có đường nào tạo super admin: cột `is_super_admin` mặc định `false` và
 * không seeder/lệnh nào bật nó, nên khu Cài đặt (chỗ dán khoá API chấm AI, đổi thương hiệu,
 * bật bảo trì…) không ai vào được.
 *
 * Chạy riêng, KHÔNG kèm dữ liệu mẫu:
 *   php artisan db:seed --class=SuperAdminSeeder
 *
 * Đổi tài khoản đích bằng biến môi trường:
 *   SUPER_ADMIN_EMAIL=co.uyen@example.com php artisan db:seed --class=SuperAdminSeeder
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('SUPER_ADMIN_EMAIL', 'teacher@example.com');

        $user = User::where('email', $email)->first();

        if ($user) {
            // Giữ nguyên mật khẩu và hồ sơ — chỉ nâng quyền.
            $user->forceFill([
                'is_super_admin' => true,
                // Super admin phải ở khu giáo viên; học sinh nâng lên thì đổi luôn vai trò.
                'role' => $user->role === UserRole::Student ? UserRole::Admin : $user->role,
            ])->save();

            $this->command?->info("Đã cấp super admin cho {$email} (giữ nguyên mật khẩu).");

            return;
        }

        $password = (string) env('SUPER_ADMIN_PASSWORD', 'Admin@123');

        User::create([
            'name' => (string) env('SUPER_ADMIN_NAME', 'Quản trị hệ thống'),
            'email' => $email,
            'password' => Hash::make($password),
            'role' => UserRole::Admin,
            'is_super_admin' => true,
            'is_active' => true,
        ]);

        $this->command?->warn("Đã tạo super admin mới: {$email} / {$password}");
        $this->command?->warn('Đổi mật khẩu ngay sau khi đăng nhập lần đầu.');
    }
}
