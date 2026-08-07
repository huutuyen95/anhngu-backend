<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StudentService
{
    /** Ký tự dễ nhầm bị loại khỏi mật khẩu tạm. */
    private const AMBIGUOUS = ['l', '1', 'I', 'O', '0', 'o'];

    /**
     * Danh sách học sinh có lọc / tìm / sắp xếp / phân trang.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? '', ['name', 'email', 'created_at'], true)
            ? $filters['sort'] : 'created_at';
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return User::query()
            ->where('role', UserRole::Student)
            ->when(! empty($filters['trashed']), fn ($q) => $q->onlyTrashed())
            ->when($filters['q'] ?? null, function ($q, $term) {
                $q->where(function ($sub) use ($term) {
                    $sub->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                });
            })
            ->when(isset($filters['is_active']) && $filters['is_active'] !== null && $filters['is_active'] !== '',
                fn ($q) => $q->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)))
            ->when($filters['classroom_id'] ?? null, fn ($q, $id) => $q->whereHas('classes', fn ($c) => $c->where('classrooms.id', $id)))
            ->with('classes:id,name')
            ->withCount(['testAttempts as in_progress_attempts_count' => fn ($q) => $q->where('status', 'in_progress')])
            ->orderBy($sort, $dir)
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }

    /**
     * Tạo học sinh + sinh mật khẩu tạm (trả về plaintext ĐÚNG MỘT LẦN).
     *
     * @param  array<string, mixed>  $data
     * @return array{user: User, password: string}
     */
    public function create(array $data): array
    {
        $password = $this->generatePassword();

        $user = DB::transaction(function () use ($data, $password) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($password),
                'role' => UserRole::Student,
                'phone' => $data['phone'] ?? null,
                'note' => $data['note'] ?? null,
                'avatar_url' => $data['avatar_url'] ?? null,
                'is_active' => true,
            ]);

            if (! empty($data['classroom_ids'])) {
                $this->syncClasses($user, $data['classroom_ids']);
            }

            return $user;
        });

        return ['user' => $user->load('classes:id,name'), 'password' => $password];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $student, array $data): User
    {
        DB::transaction(function () use ($student, $data) {
            $student->fill(array_filter(
                ['name' => $data['name'] ?? null, 'phone' => $data['phone'] ?? null, 'note' => $data['note'] ?? null, 'avatar_url' => $data['avatar_url'] ?? null],
                fn ($v, $k) => array_key_exists($k, $data),
                ARRAY_FILTER_USE_BOTH,
            ));
            $student->save();

            if (array_key_exists('classroom_ids', $data)) {
                $this->syncClasses($student, $data['classroom_ids'] ?? []);
            }
        });

        return $student->load('classes:id,name');
    }

    /** Đặt lại mật khẩu → trả plaintext tạm 1 lần. */
    public function resetPassword(User $student): string
    {
        $password = $this->generatePassword();
        $student->forceFill(['password' => Hash::make($password)])->save();

        return $password;
    }

    public function setStatus(User $student, bool $isActive): User
    {
        $student->update(['is_active' => $isActive]);

        return $student;
    }

    /**
     * @param  array{action: string, ids: array<int>, classroom_id?: int|null, mode?: string|null}  $data
     * @return int Số bản ghi bị tác động
     */
    public function bulk(array $data): int
    {
        $ids = $data['ids'];

        return match ($data['action']) {
            'lock' => User::whereIn('id', $ids)->update(['is_active' => false]),
            'unlock' => User::whereIn('id', $ids)->update(['is_active' => true]),
            'delete' => User::whereIn('id', $ids)->delete(),
            'assign_class' => $this->assignClass($ids, (int) $data['classroom_id'], $data['mode'] ?? 'add'),
            default => 0,
        };
    }

    /**
     * @param  array<int>  $ids
     */
    public function assignClass(array $ids, int $classroomId, string $mode): int
    {
        $classroom = Classroom::findOrFail($classroomId);

        DB::transaction(function () use ($ids, $classroom, $mode) {
            foreach ($ids as $id) {
                $student = User::find($id);
                if (! $student) {
                    continue;
                }
                if ($mode === 'move') {
                    // Gỡ khỏi mọi lớp cũ (nhiệm vụ đã giao KHÔNG bị huỷ).
                    $student->classes()->detach();
                }
                $student->classes()->syncWithoutDetaching([$classroom->id => ['status' => 'studying']]);
            }
        });

        return count($ids);
    }

    /**
     * Xem trước import (KHÔNG ghi DB): phân loại từng dòng ok / duplicate / error.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{rows: array<int, array<string, mixed>>, summary: array<string, int>}
     */
    public function previewImport(array $rows): array
    {
        $result = [];
        $summary = ['ok' => 0, 'duplicate' => 0, 'error' => 0];
        $seenEmails = [];

        foreach ($rows as $i => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            $reasons = [];

            if ($name === '') {
                $reasons[] = 'Thiếu họ tên';
            }
            if ($email === '') {
                $reasons[] = 'Thiếu email';
            } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $reasons[] = 'Email sai định dạng';
            }

            $status = 'ok';
            if ($reasons) {
                $status = 'error';
            } elseif (in_array($email, $seenEmails, true) || User::withTrashed()->where('email', $email)->exists()) {
                $status = 'duplicate';
                $reasons[] = 'Email đã tồn tại';
            }

            $seenEmails[] = $email;
            $summary[$status]++;

            $result[] = [
                'row' => $i + 1,
                'name' => $name,
                'email' => $email,
                'class' => $row['class'] ?? null,
                'status' => $status,
                'reasons' => $reasons,
            ];
        }

        return ['rows' => $result, 'summary' => $summary];
    }

    /**
     * Ghi thật các dòng hợp lệ (ok) trong transaction. Trả kèm mật khẩu tạm từng em.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: array<int, array<string, string>>, summary: array<string, int>}
     */
    /**
     * @param  'skip'|'update'  $onDuplicate  Dòng email trùng: bỏ qua, hoặc cập nhật HS hiện có.
     */
    public function commitImport(array $rows, string $onDuplicate = 'skip'): array
    {
        $preview = $this->previewImport($rows);
        $created = [];
        $updated = 0;

        DB::transaction(function () use ($preview, $rows, $onDuplicate, &$created, &$updated) {
            foreach ($preview['rows'] as $idx => $line) {
                $classroomIds = [];
                if (! empty($line['class'])) {
                    $classroom = Classroom::where('name', $line['class'])->first();
                    if ($classroom) {
                        $classroomIds[] = $classroom->id;
                    }
                }

                if ($line['status'] === 'ok') {
                    $result = $this->create([
                        'name' => $line['name'],
                        'email' => $line['email'],
                        'phone' => $rows[$idx]['phone'] ?? null,
                        'classroom_ids' => $classroomIds,
                    ]);
                    $created[] = ['email' => $line['email'], 'password' => $result['password']];
                } elseif ($line['status'] === 'duplicate' && $onDuplicate === 'update') {
                    $existing = User::where('email', $line['email'])->first();
                    if ($existing) {
                        $existing->update([
                            'name' => $line['name'] ?: $existing->name,
                            'phone' => $rows[$idx]['phone'] ?? $existing->phone,
                        ]);
                        if ($classroomIds !== []) {
                            $existing->classes()->syncWithoutDetaching(
                                collect($classroomIds)->mapWithKeys(fn ($id) => [$id => ['status' => 'studying']])->all()
                            );
                        }
                        $updated++;
                    }
                }
            }
        });

        return [
            'created' => $created,
            'summary' => [
                'ok' => count($created),
                'updated' => $updated,
                'duplicate' => $preview['summary']['duplicate'],
                'error' => $preview['summary']['error'],
            ],
        ];
    }

    /**
     * @param  array<int>  $classroomIds
     */
    private function syncClasses(User $student, array $classroomIds): void
    {
        $payload = collect($classroomIds)->mapWithKeys(
            fn ($id) => [$id => ['status' => 'studying']]
        )->all();

        $student->classes()->sync($payload);
    }

    /** Mật khẩu tạm 10 ký tự, bỏ ký tự dễ nhầm. */
    private function generatePassword(): string
    {
        $alphabet = collect(str_split('abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'))
            ->reject(fn ($c) => in_array($c, self::AMBIGUOUS, true))
            ->values();

        return collect(range(1, 10))
            ->map(fn () => $alphabet[random_int(0, $alphabet->count() - 1)])
            ->implode('');
    }
}
