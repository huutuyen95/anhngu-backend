<?php

namespace Tests\Feature;

use App\Models\Setting;
use Database\Seeders\SystemBrandingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemBrandingSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_student_banner_and_preserves_a_custom_value(): void
    {
        Storage::fake('public');

        $this->seed(SystemBrandingSeeder::class);

        $seededUrl = asset('storage/branding/student-menu-banner.png');

        Storage::disk('public')->assertExists('branding/student-menu-banner.png');
        $this->assertSame(
            $seededUrl,
            Setting::query()->where('key', 'brand.student.banner')->value('value'),
        );
        $this->getJson('/api/v1/public/branding')
            ->assertOk()
            ->assertJsonPath('student.banner', $seededUrl);

        Setting::query()
            ->where('key', 'brand.student.banner')
            ->update(['value' => 'https://cdn.example.test/custom-banner.png']);

        $this->seed(SystemBrandingSeeder::class);

        $this->assertSame(
            'https://cdn.example.test/custom-banner.png',
            Setting::query()->where('key', 'brand.student.banner')->value('value'),
        );
    }
}
