<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SettingChange;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SettingController extends Controller
{
    public function __construct(private readonly SettingService $settings) {}

    /** Toàn bộ nhóm + field kèm giá trị hiện tại (secret đã che). */
    public function index(): JsonResponse
    {
        $values = $this->settings->all();

        $groups = [];
        foreach ($this->settings->schema() as $groupKey => $group) {
            $fields = [];
            foreach ($group['fields'] as $key => $meta) {
                $fields[] = $this->presentField($key, $meta, $values[$key] ?? ($meta['default'] ?? null));
            }
            $groups[] = [
                'key' => $groupKey,
                'label' => $group['label'],
                'desc' => $group['desc'] ?? '',
                'icon' => $group['icon'] ?? 'settings',
                'fields' => $fields,
            ];
        }

        return response()->json([
            'groups' => $groups,
            'meta' => [
                'last_saved_at' => optional(Setting::max('updated_at'))
                    ? Setting::max('updated_at')
                    : null,
                'mail_verified' => (bool) $this->settings->get('mail.verified_at'),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $values = $request->input('values', []);
        abort_unless(is_array($values) && $values !== [], 422, 'Không có thay đổi nào để lưu.');

        // Ảnh cũ trước khi lưu — để xoá file không còn dùng sau khi lưu.
        $oldFiles = $this->currentFileValues();

        $saved = $this->settings->set($values);

        $this->cleanupReplacedFiles($oldFiles);

        return response()->json(['saved' => $saved]);
    }

    public function reset(Request $request): JsonResponse
    {
        $group = $request->input('group');
        $keys = $request->input('keys', []);

        $oldFiles = $this->currentFileValues();

        if ($group) {
            $this->settings->resetGroup($group);
        } elseif (is_array($keys) && $keys !== []) {
            $this->settings->reset($keys);
        } else {
            abort(422, 'Cần chỉ định nhóm hoặc danh sách key.');
        }

        $this->cleanupReplacedFiles($oldFiles);

        return response()->json(['message' => 'Đã khôi phục mặc định.']);
    }

    public function upload(Request $request): JsonResponse
    {
        $key = $request->input('key');
        $meta = $this->settings->field($key);
        abort_unless($meta && $meta['type'] === 'file', 422, 'Khoá cấu hình không hợp lệ.');

        $accept = $meta['accept'] ?? 'png,jpg,jpeg,svg';
        $maxKb = $meta['max_kb'] ?? 2048;

        $request->validate([
            'file' => ['required', 'file', 'mimes:'.$accept, 'max:'.$maxKb],
        ], [], ['file' => $meta['label']]);

        $path = $request->file('file')->store('branding', 'public');

        return response()->json(['url' => asset('storage/'.$path)]);
    }

    public function deleteFile(Request $request): JsonResponse
    {
        $key = $request->input('key');
        $meta = $this->settings->field($key);
        abort_unless($meta && $meta['type'] === 'file', 422, 'Khoá cấu hình không hợp lệ.');

        $current = $this->settings->get($key);
        $this->deleteBrandingFile($current);
        $this->settings->reset([$key]);

        return response()->json(['message' => 'Đã xoá tệp.']);
    }

    public function changes(Request $request): JsonResponse
    {
        $fields = $this->settings->fields();

        $paginator = SettingChange::with('changedBy:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);

        $data = collect($paginator->items())->map(fn (SettingChange $c) => [
            'id' => $c->id,
            'setting_key' => $c->setting_key,
            'label' => $fields[$c->setting_key]['label'] ?? $c->setting_key,
            'old_value' => $c->old_value,
            'new_value' => $c->new_value,
            'changed_by' => $c->changedBy?->name,
            'created_at' => $c->created_at,
            'revertible' => isset($fields[$c->setting_key]) && empty($fields[$c->setting_key]['secret']),
        ]);

        return response()->json([
            'data' => $data,
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()],
        ]);
    }

    public function revert(SettingChange $change): JsonResponse
    {
        $meta = $this->settings->field($change->setting_key);
        abort_unless($meta, 404);

        if (! empty($meta['secret'])) {
            abort(422, 'Không thể hoàn tác giá trị được mã hoá.');
        }

        // old_value đang ở dạng chuỗi hiển thị — ép về kiểu đúng rồi set lại.
        $value = $this->coerce($change->old_value, $meta);
        $this->settings->set([$change->setting_key => $value]);

        return response()->json(['message' => 'Đã hoàn tác thay đổi.']);
    }

    /** Gửi email thử bằng cấu hình đang nhập (chưa lưu). Thành công → đánh dấu verified. */
    public function mailTest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'to' => ['required', 'email'],
            'config' => ['array'],
        ]);

        $cfg = $this->resolveMailConfig($data['config'] ?? []);

        config()->set('mail.mailers.settings_test', [
            'transport' => 'smtp',
            'host' => $cfg['host'],
            'port' => $cfg['port'],
            'encryption' => $cfg['encryption'] === 'none' ? null : $cfg['encryption'],
            'username' => $cfg['username'] ?: null,
            'password' => $cfg['password'] ?: null,
            'timeout' => 10,
        ]);

        try {
            Mail::mailer('settings_test')->raw(
                'Đây là email thử từ hệ thống '.($this->settings->get('brand.center_name') ?? 'Anh ngữ Mrs Uyên').'. Nếu em nhận được thư này nghĩa là cấu hình gửi email đã đúng.',
                function ($message) use ($data, $cfg) {
                    $message->to($data['to'])
                        ->subject('[Thử] Cấu hình email đã hoạt động')
                        ->from($cfg['from_address'] ?: $cfg['username'], $cfg['from_name'] ?: 'Anh ngữ Mrs Uyên');
                }
            );
        } catch (\Throwable $e) {
            // Trả nguyên văn lỗi SMTP để cô biết sai ở đâu.
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $this->settings->markMailVerified();

        return response()->json(['ok' => true, 'message' => 'Đã gửi email thử thành công.']);
    }

    /** KHÔNG cần auth — thương hiệu cho cả 2 khu (frontend gọi lúc khởi động). */
    public function publicBranding(): JsonResponse
    {
        $g = fn (string $k) => $this->settings->get($k);

        return response()->json([
            'center_name' => $g('brand.center_name'),
            'primary_color' => $g('brand.primary_color'),
            'admin' => [
                'logo' => $g('brand.admin.logo'),
                'favicon' => $g('brand.admin.favicon'),
                'tab_title' => $g('brand.admin.tab_title'),
            ],
            'student' => [
                'logo' => $g('brand.student.logo'),
                'favicon' => $g('brand.student.favicon'),
                'tab_title' => $g('brand.student.tab_title'),
                'pwa_icon' => $g('brand.student.pwa_icon'),
                'banner' => $g('brand.student.banner'),
                'login_cover' => $g('brand.student.login_cover'),
            ],
            'maintenance' => (bool) $g('system.maintenance'),
        ]);
    }

    // ── Nội bộ ────────────────────────────────────────────────────────────

    private function presentField(string $key, array $meta, mixed $value): array
    {
        $out = [
            'key' => $key,
            'label' => $meta['label'],
            'hint' => $meta['hint'] ?? '',
            'type' => $meta['type'],
            'value' => ! empty($meta['secret'])
                ? ($value ? SettingService::SECRET_MASK : '')
                : $value,
            'default' => $meta['default'] ?? null,
            'required' => (bool) ($meta['required'] ?? false),
            'readonly' => (bool) ($meta['readonly'] ?? false),
            'secret' => (bool) ($meta['secret'] ?? false),
        ];
        if (isset($meta['unit'])) {
            $out['unit'] = $meta['unit'];
        }
        if (isset($meta['options'])) {
            $out['options'] = collect($meta['options'])->map(fn ($label, $val) => ['value' => $val, 'label' => $label])->values();
        }
        if (isset($meta['accept'])) {
            $out['accept'] = $meta['accept'];
        }

        return $out;
    }

    private function currentFileValues(): array
    {
        $out = [];
        foreach ($this->settings->fields() as $key => $meta) {
            if ($meta['type'] === 'file') {
                $out[$key] = $this->settings->get($key);
            }
        }

        return $out;
    }

    private function cleanupReplacedFiles(array $oldFiles): void
    {
        foreach ($oldFiles as $key => $old) {
            if (! $old) {
                continue;
            }
            $new = $this->settings->get($key);
            if ($new !== $old) {
                $this->deleteBrandingFile($old);
            }
        }
    }

    private function deleteBrandingFile(?string $url): void
    {
        if (! $url) {
            return;
        }
        $path = Str::after($url, '/storage/');
        if ($path && $path !== $url && Str::startsWith($path, 'branding/')) {
            Storage::disk('public')->delete($path);
        }
    }

    private function coerce(?string $raw, array $meta): mixed
    {
        if ($raw === null) {
            return $meta['default'] ?? null;
        }

        return match ($meta['type']) {
            'int' => (int) $raw,
            'float' => (float) $raw,
            'bool' => $raw === '1' || $raw === 'true',
            'json' => json_decode($raw, true),
            default => $raw,
        };
    }

    /** Ghép cấu hình mail: bắt đầu từ giá trị đã lưu, đè bằng giá trị đang nhập trên form. */
    private function resolveMailConfig(array $override): array
    {
        $keys = ['host', 'port', 'encryption', 'username', 'from_name', 'from_address'];
        $cfg = [];
        foreach ($keys as $k) {
            $cfg[$k] = $override['mail.'.$k] ?? $this->settings->get('mail.'.$k);
        }

        // Mật khẩu: gõ mới (khác chuỗi che) thì dùng, không thì lấy giá trị đã lưu.
        $pw = $override['mail.password'] ?? null;
        $cfg['password'] = ($pw && $pw !== SettingService::SECRET_MASK)
            ? $pw
            : $this->settings->get('mail.password');

        return $cfg;
    }
}
