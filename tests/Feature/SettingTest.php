<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\SettingChange;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        // Cài đặt chỉ dành cho super admin.
        return User::factory()->admin()->create(['is_super_admin' => true]);
    }

    public function test_teacher_is_forbidden(): void
    {
        $teacher = User::factory()->teacher()->create();
        $this->actingAs($teacher)->getJson('/api/v1/admin/settings')->assertForbidden();
    }

    public function test_plain_admin_without_super_flag_is_forbidden(): void
    {
        $admin = User::factory()->admin()->create(['is_super_admin' => false]);
        $this->actingAs($admin)->getJson('/api/v1/admin/settings')->assertForbidden();
    }

    public function test_index_returns_groups_with_defaults(): void
    {
        $res = $this->actingAs($this->admin())->getJson('/api/v1/admin/settings')->assertOk();

        $res->assertJsonStructure(['groups' => [['key', 'label', 'fields' => [['key', 'label', 'type', 'value', 'default']]]], 'meta']);
        $groups = collect($res->json('groups'))->pluck('key');
        $this->assertContains('exam', $groups);

        // Giá trị mặc định của exam.leave_limit = 3.
        $exam = collect($res->json('groups'))->firstWhere('key', 'exam');
        $leave = collect($exam['fields'])->firstWhere('key', 'exam.leave_limit');
        $this->assertSame(3, $leave['value']);
    }

    public function test_set_casts_types_and_persists(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->putJson('/api/v1/admin/settings', [
            'values' => ['exam.leave_limit' => 1, 'exam.allow_pause' => true],
        ])->assertOk();

        $svc = app(SettingService::class);
        $this->assertSame(1, $svc->get('exam.leave_limit'));
        $this->assertTrue($svc->get('exam.allow_pause'));
        $this->assertDatabaseHas('settings', ['key' => 'exam.leave_limit', 'value' => '1']);
    }

    public function test_value_equal_default_removes_row(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->putJson('/api/v1/admin/settings', ['values' => ['exam.leave_limit' => 5]])->assertOk();
        $this->assertDatabaseHas('settings', ['key' => 'exam.leave_limit']);

        // Đặt lại đúng default (3) → xoá bản ghi.
        $this->actingAs($admin)->putJson('/api/v1/admin/settings', ['values' => ['exam.leave_limit' => 3]])->assertOk();
        $this->assertDatabaseMissing('settings', ['key' => 'exam.leave_limit']);
    }

    public function test_reset_group_restores_defaults(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->putJson('/api/v1/admin/settings', ['values' => ['exam.leave_limit' => 1]])->assertOk();

        $this->actingAs($admin)->postJson('/api/v1/admin/settings/reset', ['group' => 'exam'])->assertOk();

        $this->assertDatabaseMissing('settings', ['key' => 'exam.leave_limit']);
        $this->assertSame(3, app(SettingService::class)->get('exam.leave_limit'));
    }

    public function test_smtp_password_is_encrypted_and_never_returned(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->putJson('/api/v1/admin/settings', [
            'values' => ['mail.password' => 'super-secret-app-pw'],
        ])->assertOk();

        // DB lưu ciphertext, không phải plaintext.
        $stored = Setting::where('key', 'mail.password')->value('value');
        $this->assertNotSame('super-secret-app-pw', $stored);
        $this->assertSame('super-secret-app-pw', Crypt::decryptString($stored));

        // API trả chuỗi che.
        $res = $this->actingAs($admin)->getJson('/api/v1/admin/settings')->assertOk();
        $mail = collect($res->json('groups'))->firstWhere('key', 'mail');
        $pw = collect($mail['fields'])->firstWhere('key', 'mail.password');
        $this->assertSame(SettingService::SECRET_MASK, $pw['value']);

        // Nội bộ vẫn đọc được plaintext để cấu hình mailer.
        $this->assertSame('super-secret-app-pw', app(SettingService::class)->get('mail.password'));
    }

    public function test_masked_password_keeps_existing_value(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->putJson('/api/v1/admin/settings', ['values' => ['mail.password' => 'first-pw']])->assertOk();

        // Gửi lại chuỗi che = giữ nguyên.
        $this->actingAs($admin)->putJson('/api/v1/admin/settings', ['values' => ['mail.password' => SettingService::SECRET_MASK]])->assertOk();
        $this->assertSame('first-pw', app(SettingService::class)->get('mail.password'));
    }

    public function test_enabling_mail_without_verify_returns_422(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->putJson('/api/v1/admin/settings', ['values' => ['mail.enabled' => true]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mail.enabled');
    }

    public function test_upload_then_replace_deletes_old_file(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $file1 = UploadedFile::fake()->image('logo1.png');
        $url1 = $this->actingAs($admin)->post('/api/v1/admin/settings/upload', ['key' => 'brand.student.logo', 'file' => $file1])
            ->assertOk()->json('url');
        $path1 = Str::after($url1, '/storage/');
        Storage::disk('public')->assertExists($path1);

        $this->actingAs($admin)->putJson('/api/v1/admin/settings', ['values' => ['brand.student.logo' => $url1]])->assertOk();

        // Tải ảnh mới rồi lưu → ảnh cũ bị xoá.
        $file2 = UploadedFile::fake()->image('logo2.png');
        $url2 = $this->actingAs($admin)->post('/api/v1/admin/settings/upload', ['key' => 'brand.student.logo', 'file' => $file2])
            ->assertOk()->json('url');
        $this->actingAs($admin)->putJson('/api/v1/admin/settings', ['values' => ['brand.student.logo' => $url2]])->assertOk();

        Storage::disk('public')->assertMissing($path1);
        Storage::disk('public')->assertExists(Str::after($url2, '/storage/'));
    }

    public function test_revert_change_restores_previous_value(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->putJson('/api/v1/admin/settings', ['values' => ['exam.leave_limit' => 1]])->assertOk();

        $change = SettingChange::where('setting_key', 'exam.leave_limit')->latest('id')->first();
        $this->assertNotNull($change);

        $this->actingAs($admin)->postJson("/api/v1/admin/settings/changes/{$change->id}/revert")->assertOk();

        // old_value là 3 (default) → quay lại 3.
        $this->assertSame(3, app(SettingService::class)->get('exam.leave_limit'));
    }

    public function test_public_branding_is_accessible_without_auth(): void
    {
        app(SettingService::class)->set(['brand.student.banner' => 'http://localhost/storage/branding/student-banner.jpg']);

        $this->getJson('/api/v1/public/branding')
            ->assertOk()
            ->assertJsonStructure(['center_name', 'primary_color', 'admin', 'student', 'maintenance'])
            ->assertJsonPath('student.banner', 'http://localhost/storage/branding/student-banner.jpg');
    }

    public function test_maintenance_blocks_students_not_teachers(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->putJson('/api/v1/admin/settings', ['values' => ['system.maintenance' => true]])->assertOk();

        $student = User::factory()->create();
        $this->actingAs($student)->getJson('/api/v1/tests')->assertStatus(503);

        // Giáo viên/admin vẫn vào được.
        $this->actingAs($admin)->getJson('/api/v1/dashboard')->assertOk();
    }
}
