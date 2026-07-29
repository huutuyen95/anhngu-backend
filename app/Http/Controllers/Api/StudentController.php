<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\BulkStudentRequest;
use App\Http\Requests\Student\ImportStudentRequest;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Imports\StudentsImport;
use App\Models\User;
use App\Services\StudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentController extends Controller
{
    public function __construct(private readonly StudentService $students) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->students->list($request->only([
            'q', 'classroom_id', 'is_active', 'trashed', 'sort', 'dir', 'per_page',
        ]));

        return response()->json([
            'data' => StudentResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(StoreStudentRequest $request): JsonResponse
    {
        $result = $this->students->create($request->validated());

        return response()->json([
            'student' => new StudentResource($result['user']),
            'temp_password' => $result['password'],
        ], 201);
    }

    public function update(UpdateStudentRequest $request, int $student): JsonResponse
    {
        $model = $this->resolve($student);
        $updated = $this->students->update($model, $request->validated());

        return response()->json(['student' => new StudentResource($updated)]);
    }

    public function destroy(Request $request, int $student): JsonResponse
    {
        $model = $this->resolve($student, withTrashed: true);

        if ($request->boolean('force')) {
            $model->forceDelete();

            return response()->json(['message' => 'Đã xoá vĩnh viễn.']);
        }

        $model->delete();

        return response()->json(['message' => 'Đã chuyển vào thùng rác.']);
    }

    public function restore(int $student): JsonResponse
    {
        $model = $this->resolve($student, withTrashed: true);
        $model->restore();

        return response()->json(['student' => new StudentResource($model->load('classes:id,name'))]);
    }

    public function status(Request $request, int $student): JsonResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $model = $this->resolve($student);

        return response()->json([
            'student' => new StudentResource($this->students->setStatus($model, $data['is_active'])),
        ]);
    }

    public function bulk(BulkStudentRequest $request): JsonResponse
    {
        $affected = $this->students->bulk($request->validated());

        return response()->json(['affected' => $affected]);
    }

    public function resetPassword(int $student): JsonResponse
    {
        $model = $this->resolve($student);
        $password = $this->students->resetPassword($model);

        return response()->json(['temp_password' => $password]);
    }

    public function import(ImportStudentRequest $request): JsonResponse
    {
        $sheets = Excel::toArray(new StudentsImport, $request->file('file'));
        $rows = $sheets[0] ?? [];

        if ($request->boolean('dry_run')) {
            return response()->json($this->students->previewImport($rows));
        }

        return response()->json($this->students->commitImport($rows));
    }

    public function importTemplate(): BinaryFileResponse
    {
        return Excel::download(
            new \App\Exports\StudentsTemplateExport,
            'mau-import-hoc-sinh.xlsx',
        );
    }

    /** Lấy học sinh theo id, đảm bảo đúng vai trò student. */
    private function resolve(int $id, bool $withTrashed = false): User
    {
        $query = User::query()->where('role', UserRole::Student);
        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->findOrFail($id);
    }
}
