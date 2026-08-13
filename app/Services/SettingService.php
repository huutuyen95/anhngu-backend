<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\SettingChange;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SettingService
{
    private const CACHE_KEY = 'app.settings.all';

    public const SECRET_MASK = '••••••••';

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
            $rows = Setting::pluck('value', 'key');

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
        $clean = [];

        // Lọc: bỏ readonly; bỏ secret khi để trống / còn là chuỗi che (giữ nguyên).
        foreach ($kv as $key => $value) {
            $meta = $fields[$key] ?? null;
            if (! $meta || ! empty($meta['readonly'])) {
                continue;
            }
            if (! empty($meta['secret']) && ($value === '' || $value === null || $value === self::SECRET_MASK)) {
                continue; // gửi rỗng/che = giữ nguyên
            }
            $clean[$key] = $value;
        }

        $this->guardMailEnable($clean);

        // Validate theo rule của từng field.
        $rules = [];
        foreach ($clean as $key => $value) {
            $meta = $fields[$key];
            $rules[$key] = $this->rulesFor($meta);
        }
        Validator::make($clean, $rules, [], $this->attributeNames($clean))->validate();

        $current = $this->all();
        $userId = Auth::id();

        DB::transaction(function () use ($clean, $fields, $current, $userId) {
            foreach ($clean as $key => $value) {
                $meta = $fields[$key];
                $oldValue = $current[$key] ?? ($meta['default'] ?? null);

                if ($this->equalsDefault($value, $meta)) {
                    Setting::where('key', $key)->delete();
                } else {
                    Setting::updateOrCreate(
                        ['key' => $key],
                        [
                            'value' => $this->serializeForStorage($value, $meta),
                            'type' => $meta['type'],
                            'group' => $this->groupOf($key),
                            'updated_by' => $userId,
                        ]
                    );
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

        DB::transaction(function () use ($keys, $fields, $current, $userId) {
            foreach ($keys as $key) {
                $meta = $fields[$key] ?? null;
                if (! $meta) {
                    continue;
                }
                $old = $current[$key] ?? null;
                Setting::where('key', $key)->delete();
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
        Setting::updateOrCreate(
            ['key' => 'mail.verified_at'],
            ['value' => now()->toISOString(), 'type' => 'string', 'group' => 'mail', 'updated_by' => Auth::id()]
        );
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    // ── Nội bộ ────────────────────────────────────────────────────────────

    private function guardMailEnable(array $clean): void
    {
        if (($clean['mail.enabled'] ?? false) && ! $this->get('mail.verified_at')) {
            throw ValidationException::withMessages([
                'mail.enabled' => ['Cần gửi email thử thành công ít nhất một lần trước khi bật.'],
            ]);
        }
    }

    private function rulesFor(array $meta): array
    {
        $rules = $meta['rules'] ?? [];
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }
        // Rule nền theo type để chặn kiểu sai ngay cả khi field không khai rule riêng.
        $base = match ($meta['type']) {
            'int' => ['integer'],
            'float' => ['numeric'],
            'bool' => ['boolean'],
            'json' => ['array'],
            default => ['string'],
        };
        if (! empty($meta['required'])) {
            array_unshift($base, 'required');
        } else {
            array_unshift($base, 'nullable');
        }

        return array_values(array_unique([...$base, ...$rules]));
    }

    private function attributeNames(array $clean): array
    {
        $fields = $this->fields();

        return collect($clean)->keys()
            ->mapWithKeys(fn ($k) => [$k => $fields[$k]['label'] ?? $k])
            ->all();
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
        SettingChange::create([
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
