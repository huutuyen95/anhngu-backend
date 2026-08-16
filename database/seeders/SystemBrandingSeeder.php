<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SystemBrandingSeeder extends Seeder
{
    private const BANNER_PATH = 'branding/student-menu-banner.png';

    public function run(): void
    {
        $source = database_path('seeders/assets/student-menu-banner.png');

        if (! is_file($source)) {
            throw new RuntimeException("Không tìm thấy banner mặc định: {$source}");
        }

        Storage::disk('public')->put(self::BANNER_PATH, file_get_contents($source));

        // Chỉ tạo mặc định trên database mới; không ghi đè ảnh super admin đã chọn.
        Setting::firstOrCreate(
            ['key' => 'brand.student.banner'],
            [
                'value' => asset('storage/'.self::BANNER_PATH),
                'type' => 'file',
                'group' => 'brand',
                'updated_by' => null,
            ],
        );

        app(SettingService::class)->flush();
    }
}
