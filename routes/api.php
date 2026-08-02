<?php

use App\Http\Controllers\Api\AdminAttemptController;
use App\Http\Controllers\Api\AdminTestController;
use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClassroomController;
use App\Http\Controllers\Api\ClassSessionController;
use App\Http\Controllers\Api\ClassStudentController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeckController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\SessionItemController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\TestAttemptController;
use App\Http\Controllers\Api\TestController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('decks', [DeckController::class, 'index']);
        Route::get('decks/{deck}', [DeckController::class, 'show']);
        Route::post('decks/{deck}/progress', [DeckController::class, 'saveProgress']);

        Route::get('tests', [TestController::class, 'index']);
        Route::get('tests/{test}', [TestController::class, 'show']);
        Route::post('tests/{test}/attempts', [TestAttemptController::class, 'start']);
        Route::put('attempts/{attempt}/answers', [TestAttemptController::class, 'saveAnswers']);
        Route::post('attempts/{attempt}/answers/{question}/audio', [TestAttemptController::class, 'uploadAudio']);
        Route::delete('attempts/{attempt}/answers/{question}/audio', [TestAttemptController::class, 'deleteAudio']);
        Route::post('attempts/{attempt}/submit', [TestAttemptController::class, 'submit']);
        Route::get('attempts/{attempt}/result', [TestAttemptController::class, 'result']);

        Route::middleware('role:teacher,admin')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'index']);

            Route::get('media/class-covers', [MediaController::class, 'classCovers']);
            Route::post('media/upload', [MediaController::class, 'upload']);

            Route::get('classrooms/{classroom}/overview', [ClassroomController::class, 'overview']);
            Route::get('classrooms/{classroom}/sessions', [ClassSessionController::class, 'index']);
            Route::put('classrooms/{classroom}/sessions/sync', [ClassSessionController::class, 'sync']);
            Route::post('classrooms/{classroom}/sessions', [ClassSessionController::class, 'store']);
            Route::post('classrooms/{classroom}/remind', [AssignmentController::class, 'remind']);

            // Báo cáo lớp
            Route::get('classrooms/{classroom}/report/export', [ReportController::class, 'export']);
            Route::get('classrooms/{classroom}/report', [ReportController::class, 'show']);

            // Học viên trong lớp
            Route::get('classrooms/{classroom}/students', [ClassStudentController::class, 'index']);
            Route::post('classrooms/{classroom}/students/quick', [ClassStudentController::class, 'quick']);
            Route::post('classrooms/{classroom}/students', [ClassStudentController::class, 'store']);
            Route::delete('classrooms/{classroom}/students/{userId}', [ClassStudentController::class, 'destroy']);

            Route::apiResource('classrooms', ClassroomController::class);

            // Điểm danh theo buổi
            Route::get('sessions/{session}/attendances/export', [AttendanceController::class, 'export']);
            Route::get('sessions/{session}/attendances', [AttendanceController::class, 'index']);
            Route::put('sessions/{session}/attendances/bulk', [AttendanceController::class, 'bulk']);

            Route::patch('sessions/reorder', [ClassSessionController::class, 'reorder']);
            Route::put('sessions/{session}', [ClassSessionController::class, 'update']);
            Route::delete('sessions/{session}', [ClassSessionController::class, 'destroy']);

            Route::get('session-items', [SessionItemController::class, 'index']);
            Route::delete('session-items/{sessionItem}', [SessionItemController::class, 'destroy']);

            Route::get('assignable-content', [ContentController::class, 'index']);
            Route::post('assignments', [AssignmentController::class, 'store']);

            // Đề thi: CRUD + editor cấu trúc (Part → Section → Question → Option)
            Route::put('admin/tests/{test}/structure', [AdminTestController::class, 'saveStructure']);
            Route::apiResource('admin/tests', AdminTestController::class);

            // Chấm bài (chủ yếu writing — cô chấm tay)
            Route::get('admin/attempts', [AdminAttemptController::class, 'index']);
            Route::get('admin/attempts/{attempt}', [AdminAttemptController::class, 'show']);
            Route::post('admin/attempts/{attempt}/grade', [AdminAttemptController::class, 'grade']);

            Route::get('students/import-template', [StudentController::class, 'importTemplate']);
            Route::post('students/import', [StudentController::class, 'import']);
            Route::post('students/bulk', [StudentController::class, 'bulk']);
            Route::post('students/{student}/restore', [StudentController::class, 'restore']);
            Route::post('students/{student}/reset-password', [StudentController::class, 'resetPassword']);
            Route::patch('students/{student}/status', [StudentController::class, 'status']);
            Route::apiResource('students', StudentController::class)
                ->only(['index', 'store', 'update', 'destroy']);
        });
    });
});
