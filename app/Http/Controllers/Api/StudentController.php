<?php

namespace App\Http\Controllers\Api;

use App\Exports\StudentsTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\BulkStudentRequest;
use App\Http\Requests\Student\CheckStudentEmailRequest;
use App\Http\Requests\Student\DeleteStudentRequest;
use App\Http\Requests\Student\ImportStudentRequest;
use App\Http\Requests\Student\ListStudentsRequest;
use App\Http\Requests\Student\SetStudentStatusRequest;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Http\Responses\ApiResponse;
use App\Services\StudentService;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentController extends Controller
{
    public function __construct(private readonly StudentService $students) {}

    public function index(ListStudentsRequest $request): JsonResponse
    {
        $page = $this->students->list($request->validated());

        return ApiResponse::paginated(StudentResource::collection($page->items()), $page);
    }

    public function store(StoreStudentRequest $request): JsonResponse
    {
        $result = $this->students->create($request->validated());

        return ApiResponse::resource(new StudentResource($result['user']), 'student', 201, [
            'temp_password' => $result['password'],
        ]);
    }

    public function update(UpdateStudentRequest $request, int $student): JsonResponse
    {
        $model = $this->students->resolve($student);
        $updated = $this->students->update($model, $request->validated());

        return ApiResponse::resource(new StudentResource($updated), 'student');
    }

    public function destroy(DeleteStudentRequest $request, int $student): JsonResponse
    {
        $model = $this->students->resolve($student, withTrashed: true);

        if ($request->boolean('force')) {
            $this->students->delete($model, true);

            return ApiResponse::message('Đã xoá vĩnh viễn.');
        }

        $this->students->delete($model, false);

        return ApiResponse::message('Đã chuyển vào thùng rác.');
    }

    public function restore(int $student): JsonResponse
    {
        $model = $this->students->restore($this->students->resolve($student, withTrashed: true));

        return ApiResponse::resource(new StudentResource($model), 'student');
    }

    public function status(SetStudentStatusRequest $request, int $student): JsonResponse
    {
        $data = $request->validated();
        $model = $this->students->resolve($student);

        return ApiResponse::resource(new StudentResource($this->students->setStatus($model, $data['is_active'])), 'student');
    }

    public function bulk(BulkStudentRequest $request): JsonResponse
    {
        $affected = $this->students->bulk($request->validated());

        return response()->json(['affected' => $affected]);
    }

    public function resetPassword(int $student): JsonResponse
    {
        $model = $this->students->resolve($student);
        $password = $this->students->resetPassword($model);

        return response()->json(['temp_password' => $password]);
    }

    public function import(ImportStudentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $onDuplicate = ($data['on_duplicate'] ?? null) === 'update' ? 'update' : 'skip';

        return response()->json($this->students->importFile(
            $request->file('file'),
            $request->boolean('dry_run'),
            $onDuplicate,
            (int) ($data['offset'] ?? 0),
            isset($data['limit']) ? (int) $data['limit'] : null,
        ));
    }

    /** Kiểm tra email đã tồn tại chưa (kể cả đã xoá mềm) — dùng cho blur ở form thêm/sửa. */
    public function checkEmail(CheckStudentEmailRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json(['available' => $this->students->emailAvailable($data['email'], $data['ignore_id'] ?? null)]);
    }

    public function importTemplate(): BinaryFileResponse
    {
        return Excel::download(
            new StudentsTemplateExport,
            'mau-import-hoc-sinh.xlsx',
        );
    }
}
