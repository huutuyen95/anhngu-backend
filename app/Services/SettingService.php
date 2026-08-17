<?php

namespace App\Services;

use App\Models\SettingChange;
use App\Repositories\SettingRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SettingService
{
    private const CACHE_KEY = 'app.settings.all';

    public const SECRET_MASK = '••••••••';

    public function __construct(private readonly SettingRepository $repository) {}

    /** Toàn bộ schema (nhóm + field) từ config/appsettings.php. */
    public function schema(): array
    {
        return config('appsettings.groups', []);
    }

    /** Map phẳng key => meta của field, gộp mọi nhóm. */
    public function fields(): array
    {
        $out = [];
        foreach ($this->schema() as $group) {
            foreach ($group['fields'] as $key => $meta) {
                $out[$key] = $meta;
            }
        }

        return $out;
    }

    public function field(string $key): ?array
    {
        return $this->fields()[$key] ?? null;
    }

    /** Mảng key => giá trị đã cast (default + đè bởi DB), có cache. */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $fields = $this->fields();
            $rows = $this->repository->values();

            $out = [];
            foreach ($fields as $key => $meta) {
                $out[$key] = array_key_exists($key, $rows->all())
                    ? $this->castFromStorage($rows[$key], $meta)
                    : ($meta['default'] ?? null);
            }

            return $out;
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    /**
     * Validate + ghi + log + xoá cache. Giá trị bằng default → xoá bản ghi (về mặc định).
     * Trả về mảng key => giá trị đã lưu (đã cast).
     */
    public function set(array $kv): array
    {
        $fields = $this->fields();
        $clean = $this->filterWritableValues($kv);

        $this->guardMailEnable($clean);

        $current = $this->all();
        $userId = Auth::id();

        $this->repository->transaction(function () use ($clean, $fields, $current, $userId) {
            foreach ($clean as $key => $value) {
                $meta = $fields[$key];
                $oldValue = $current[$key] ?? ($meta['default'] ?? null);

                if ($this->equalsDefault($value, $meta)) {
                    $this->repository->delete($key);
                } else {
                    $this->repository->upsert($key, [
                        'value' => $this->serializeForStorage($value, $meta),
                        'type' => $meta['type'],
                        'group' => $this->groupOf($key),
                        'updated_by' => $userId,
                    ]);
                }

                $this->logChange($key, $oldValue, $value, $meta, $userId);
            }
        });

        $this->flush();

        $after = $this->all();

        return collect(array_keys($clean))->mapWithKeys(fn ($k) => [$k => $after[$k]])->all();
    }

    /** Xoá bản ghi (về default) cho danh sách key hoặc cả nhóm. */
    public function reset(array $keys): void
    {
        $fields = $this->fields();
        $current = $this->all();
        $userId = Auth::id();

        $this->repository->transaction(function () use ($keys, $fields, $current, $userId) {
            foreach ($keys as $key) {
                $meta = $fields[$key] ?? null;
                if (! $meta) {
                    continue;
                }
                $old = $current[$key] ?? null;
                $this->repository->delete($key);
                if (! $this->valuesEqual($old, $meta['default'] ?? null, $meta)) {
                    $this->logChange($key, $old, $meta['default'] ?? null, $meta, $userId);
                }
            }
        });

        $this->flush();
    }

    public function resetGroup(string $group): void
    {
        $keys = array_keys($this->schema()[$group]['fields'] ?? []);
        $this->reset($keys);
    }

    /** Đánh dấu email đã gửi thử thành công (mở khoá bật email). */
    public function markMailVerified(): void
    {
        $this->repository->upsert('mail.verified_at', [
            'value' => now()->toISOString(),
            'type' => 'string',
            'group' => 'mail',
            'updated_by' => Auth::id(),
        ]);
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function indexPayload(): array
    {
        $values = $this->all();
        $groups = [];
        foreach ($this->schema() as $groupKey => $group) {
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

        return [
            'groups' => $groups,
            'meta' => [
                'last_saved_at' => $this->repository->lastUpdatedAt(),
                'mail_verified' => (bool) $this->get('mail.verified_at'),
            ],
        ];
    }

    public function updateSettings(array $values): array
    {
        $oldFiles = $this->currentFileValues();
        $saved = $this->set($values);
        $this->cleanupReplacedFiles($oldFiles);

        return $saved;
    }

    public function resetSettings(array $data): void
    {
        $oldFiles = $this->currentFileValues();
        if (! empty($data['group'])) {
            $this->resetGroup($data['group']);
        } else {
            $this->reset($data['keys']);
        }
        $this->cleanupReplacedFiles($oldFiles);
    }

    public function uploadFile(UploadedFile $file): string
    {
        $path = $file->store('branding', 'public');

        return asset('storage/'.$path);
    }

    public function deleteFile(string $key): void
    {
        $this->deleteBrandingFile($this->get($key));
        $this->reset([$key]);
    }

    public function changesPayload(): array
    {
        $fields = $this->fields();
        $paginator = $this->repository->changes();
        $data = collect($paginator->items())->map(fn (SettingChange $change) => [
            'id' => $change->id,
            'setting_key' => $change->setting_key,
            'label' => $fields[$change->setting_key]['label'] ?? $change->setting_key,
            'old_value' => $change->old_value,
            'new_value' => $change->new_value,
            'changed_by' => $change->changedBy?->name,
            'created_at' => $change->created_at,
            'revertible' => isset($fields[$change->setting_key]) && empty($fields[$change->setting_key]['secret']),
        ]);

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function revert(SettingChange $change): void
    {
        $meta = $this->field($change->setting_key);
        abort_unless($meta, 404);
        abort_if(! empty($meta['secret']), 422, 'Không thể hoàn tác giá trị được mã hoá.');
        $this->set([$change->setting_key => $this->coerce($change->old_value, $meta)]);
    }

    public function sendTestMail(array $data): array
    {
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
                'Đây là email thử từ hệ thống '.($this->get('brand.center_name') ?? 'Anh ngữ Mrs Uyên').'. Nếu em nhận được thư này nghĩa là cấu hình gửi email đã đúng.',
                function ($message) use ($data, $cfg): void {
                    $message->to($data['to'])
                        ->subject('[Thử] Cấu hình email đã hoạt động')
                        ->from($cfg['from_address'] ?: $cfg['username'], $cfg['from_name'] ?: 'Anh ngữ Mrs Uyên');
                }
            );
        } catch (\Throwable $exception) {
            return ['ok' => false, 'message' => $exception->getMessage()];
        }

        $this->markMailVerified();

        return ['ok' => true, 'message' => 'Đã gửi email thử thành công.'];
    }

    public function publicBranding(): array
    {
        return [
            'center_name' => $this->get('brand.center_name'),
            'primary_color' => $this->get('brand.primary_color'),
            'admin' => [
                'logo' => $this->get('brand.admin.logo'),
                'favicon' => $this->get('brand.admin.favicon'),
                'tab_title' => $this->get('brand.admin.tab_title'),
            ],
            'student' => [
                'logo' => $this->get('brand.student.logo'),
                'favicon' => $this->get('brand.student.favicon'),
                'tab_title' => $this->get('brand.student.tab_title'),
                'pwa_icon' => $this->get('brand.student.pwa_icon'),
                'banner' => $this->get('brand.student.banner'),
                'login_cover' => $this->get('brand.student.login_cover'),
            ],
            'maintenance' => (bool) $this->get('system.maintenance'),
        ];
    }

    // ── Nội bộ ────────────────────────────────────────────────────────────

    private function presentField(string $key, array $meta, mixed $value): array
    {
        $field = [
            'key' => $key,
            'label' => $meta['label'],
            'hint' => $meta['hint'] ?? '',
            'type' => $meta['type'],
            'value' => ! empty($meta['secret']) ? ($value ? self::SECRET_MASK : '') : $value,
            'default' => $meta['default'] ?? null,
            'required' => (bool) ($meta['required'] ?? false),
            'readonly' => (bool) ($meta['readonly'] ?? false),
            'secret' => (bool) ($meta['secret'] ?? false),
        ];
        foreach (['unit', 'accept'] as $attribute) {
            if (isset($meta[$attribute])) {
                $field[$attribute] = $meta[$attribute];
            }
        }
        if (isset($meta['options'])) {
            $field['options'] = collect($meta['options'])
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values();
        }

        return $field;
    }

    private function currentFileValues(): array
    {
        $files = [];
        foreach ($this->fields() as $key => $meta) {
            if ($meta['type'] === 'file') {
                $files[$key] = $this->get($key);
            }
        }

        return $files;
    }

    private function cleanupReplacedFiles(array $oldFiles): void
    {
        foreach ($oldFiles as $key => $old) {
            if ($old && $this->get($key) !== $old) {
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
        if ($path !== $url && Str::startsWith($path, 'branding/')) {
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

    private function resolveMailConfig(array $override): array
    {
        $config = [];
        foreach (['host', 'port', 'encryption', 'username', 'from_name', 'from_address'] as $key) {
            $config[$key] = $override['mail.'.$key] ?? $this->get('mail.'.$key);
        }
        $password = $override['mail.password'] ?? null;
        $config['password'] = ($password && $password !== self::SECRET_MASK)
            ? $password
            : $this->get('mail.password');

        return $config;
    }

    private function guardMailEnable(array $clean): void
    {
        if (($clean['mail.enabled'] ?? false) && ! $this->get('mail.verified_at')) {
            throw ValidationException::withMessages([
                'mail.enabled' => ['Cần gửi email thử thành công ít nhất một lần trước khi bật.'],
            ]);
        }
    }

    public function filterWritableValues(array $values): array
    {
        $fields = $this->fields();
        $clean = [];
        foreach ($values as $key => $value) {
            $meta = $fields[$key] ?? null;
            if (! $meta || ! empty($meta['readonly'])) {
                continue;
            }
            if (! empty($meta['secret']) && ($value === '' || $value === null || $value === self::SECRET_MASK)) {
                continue;
            }
            $clean[$key] = $value;
        }

        return $clean;
    }

    private function groupOf(string $key): string
    {
        foreach ($this->schema() as $group => $def) {
            if (array_key_exists($key, $def['fields'])) {
                return $group;
            }
        }

        return 'misc';
    }

    private function castFromStorage(mixed $raw, array $meta): mixed
    {
        if ($raw === null) {
            return $meta['default'] ?? null;
        }
        if (! empty($meta['secret'])) {
            try {
                return Crypt::decryptString($raw);
            } catch (\Throwable) {
                return '';
            }
        }

        return match ($meta['type']) {
            'int' => (int) $raw,
            'float' => (float) $raw,
            'bool' => $raw === '1' || $raw === 1 || $raw === true,
            'json' => json_decode($raw, true),
            default => (string) $raw,
        };
    }

    private function serializeForStorage(mixed $value, array $meta): string
    {
        if (! empty($meta['secret'])) {
            return Crypt::encryptString((string) $value);
        }

        return match ($meta['type']) {
            'int' => (string) (int) $value,
            'float' => (string) (float) $value,
            'bool' => $value ? '1' : '0',
            'json' => json_encode($value),
            default => (string) $value,
        };
    }

    private function equalsDefault(mixed $value, array $meta): bool
    {
        if (! empty($meta['secret'])) {
            return false; // secret luôn lưu (không so ciphertext)
        }

        return $this->valuesEqual($value, $meta['default'] ?? null, $meta);
    }

    private function valuesEqual(mixed $a, mixed $b, array $meta): bool
    {
        return match ($meta['type']) {
            'int' => (int) $a === (int) $b,
            'float' => (float) $a === (float) $b,
            'bool' => (bool) $a === (bool) $b,
            'json' => json_encode($a) === json_encode($b),
            default => (string) $a === (string) $b,
        };
    }

    private function logChange(string $key, mixed $old, mixed $new, array $meta, ?int $userId): void
    {
        $this->repository->logChange([
            'setting_key' => $key,
            'old_value' => $this->displayValue($old, $meta),
            'new_value' => $this->displayValue($new, $meta),
            'changed_by' => $userId,
            'created_at' => now(),
        ]);
    }

    /** Giá trị dạng chuỗi để lưu lịch sử (secret luôn che). */
    public function displayValue(mixed $value, array $meta): ?string
    {
        if (! empty($meta['secret'])) {
            return self::SECRET_MASK;
        }
        if ($value === null) {
            return null;
        }

        return match ($meta['type']) {
            'bool' => $value ? '1' : '0',
            'json' => json_encode($value),
            default => (string) $value,
        };
    }
}
