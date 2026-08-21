<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Imports\StudentsImport;
use App\Models\Classroom;
use App\Models\User;
use App\Repositories\ClassroomRepository;
use App\Repositories\StudentRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class StudentService
{
    public function __construct(private readonly ClassroomRepository $classrooms, private readonly StudentRepository $students) {}

    public function classroomStudents(Classroom $classroom)
    {
        return $this->classrooms->students($classroom);
    }

    public function attachToClassroom(Classroom $classroom, array $ids): void
    {
        $this->classrooms->attachStudents($classroom, $ids);
    }

    public function detachFromClassroom(Classroom $classroom, int $id): void
    {
        $this->classrooms->detachStudent($classroom, $id);
    }

    /** Ký tự dễ nhầm bị loại khỏi mật khẩu tạm. */
    private const AMBIGUOUS = ['l', '1', 'I', 'O', '0', 'o'];

    /**
     * Danh sách học sinh có lọc / tìm / sắp xếp / phân trang.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->students->paginate($filters);
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

        $user = $this->students->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($password),
            'role' => UserRole::Student,
            'phone' => $data['phone'] ?? null,
            'note' => $data['note'] ?? null,
            'avatar_url' => $data['avatar_url'] ?? null,
            'is_active' => true,
        ], $data['classroom_ids'] ?? []);

        return ['user' => $user, 'password' => $password];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $student, array $data): User
    {
        $attributes = array_filter(
            ['name' => $data['name'] ?? null, 'phone' => $data['phone'] ?? null, 'note' => $data['note'] ?? null, 'avatar_url' => $data['avatar_url'] ?? null],
            fn ($v, $k) => array_key_exists($k, $data),
            ARRAY_FILTER_USE_BOTH,
        );

        return $this->students->update($student, $attributes, array_key_exists('classroom_ids', $data) ? ($data['classroom_ids'] ?? []) : null);
    }

    /** Đặt lại mật khẩu → trả plaintext tạm 1 lần. */
    public function resetPassword(User $student): string
    {
        $password = $this->generatePassword();
        $this->students->updateFields($student, ['password' => Hash::make($password)]);

        return $password;
    }

    public function setStatus(User $student, bool $isActive): User
    {
        return $this->students->updateFields($student, ['is_active' => $isActive]);
    }

    /**
     * @param  array{action: string, ids: array<int>, classroom_id?: int|null, mode?: string|null}  $data
     * @return int Số bản ghi bị tác động
     */
    public function bulk(array $data): int
    {
        $ids = $data['ids'];

        return match ($data['action']) {
            'lock' => $this->students->bulkUpdate($ids, ['is_active' => false]),
            'unlock' => $this->students->bulkUpdate($ids, ['is_active' => true]),
            'delete' => $this->students->bulkDelete($ids),
            'assign_class' => $this->assignClass($ids, (int) $data['classroom_id'], $data['mode'] ?? 'add'),
            default => 0,
        };
    }

    /**
     * @param  array<int>  $ids
     */
    public function assignClass(array $ids, int $classroomId, string $mode): int
    {
        return $this->students->assignClass($ids, $classroomId, $mode);
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
            } elseif (in_array($email, $seenEmails, true) || $this->students->emailExists($email)) {
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

        $this->students->transaction(function () use ($preview, $rows, $onDuplicate, &$created, &$updated) {
            foreach ($preview['rows'] as $idx => $line) {
                $classroomIds = [];
                if (! empty($line['class'])) {
                    $classroom = $this->students->classroomByName($line['class']);
                    if ($classroom) {
                        $classroomIds[] = $classroom->id;
                    }
                }

                if ($line['status'] === 'ok') {
                    $result = $this->create([
                        'name' => $line['name'],
                        'email' => $line['email'],
                        'phone' => $rows[$idx]['phone'] ?? null,
                        'note' => $rows[$idx]['note'] ?? null,
                        'classroom_ids' => $classroomIds,
                    ]);
                    $created[] = ['email' => $line['email'], 'password' => $result['password']];
                } elseif ($line['status'] === 'duplicate' && $onDuplicate === 'update') {
                    $existing = $this->students->findByEmail($line['email']);
                    if ($existing) {
                        $this->students->updateFields($existing, [
                            'name' => $line['name'] ?: $existing->name,
                            'phone' => $rows[$idx]['phone'] ?? $existing->phone,
                            'note' => array_key_exists('note', $rows[$idx]) ? $rows[$idx]['note'] : $existing->note,
                        ]);
                        if ($classroomIds !== []) {
                            $this->classrooms->attachStudents($classroom, [$existing->id]);
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
    public function resolve(int $id, bool $withTrashed = false): User
    {
        return $this->students->resolve($id, $withTrashed);
    }

    public function delete(User $student, bool $force): void
    {
        $this->students->delete($student, $force);
    }

    public function restore(User $student): User
    {
        return $this->students->restore($student);
    }

    public function emailAvailable(string $email, ?int $ignoreId): bool
    {
        return ! $this->students->emailExists($email, $ignoreId);
    }

    public function importFile(
        UploadedFile $file,
        bool $dryRun,
        string $onDuplicate,
        int $offset = 0,
        ?int $limit = null,
    ): array {
        $rows = Excel::toArray(new StudentsImport, $file)[0] ?? [];

        if (! $dryRun && $limit === null && count($rows) > 100) {
            throw ValidationException::withMessages([
                'file' => ['File trên 100 dòng cần được import theo từng batch. Vui lòng tải lại trang và thử lại.'],
            ]);
        }

        // Commit theo batch để việc hash mật khẩu của file lớn không làm một request
        // vượt max_execution_time của PHP-FPM. Dry-run vẫn luôn xem toàn bộ file.
        if (! $dryRun && $limit !== null) {
            $rows = array_slice($rows, $offset, $limit);
        }

        return $dryRun ? $this->previewImport($rows) : $this->commitImport($rows, $onDuplicate);
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
