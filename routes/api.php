<?php

use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClassroomController;
use App\Http\Controllers\Api\ClassSessionController;
use App\Http\Controllers\Api\ClassStudentController;
use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\DictionaryController;
use App\Http\Controllers\Api\DocumentAttachmentController;
use App\Http\Controllers\Api\DocumentCategoryController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\StudentDocumentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeckController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\SessionItemController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\StudentDeckController;
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

        // Tài liệu & Bài giảng — khu học sinh
        Route::get('library/documents', [StudentDocumentController::class, 'library']);
        Route::get('documents/{document}/read', [StudentDocumentController::class, 'read']);
        Route::post('documents/{document}/view', [StudentDocumentController::class, 'view']);
        Route::get('dictionary', [DictionaryController::class, 'lookup']);
        Route::post('me/vocab', [DictionaryController::class, 'saveVocab']);

        // Từ vựng — khu học sinh
        Route::get('library/decks', [StudentDeckController::class, 'library']);
        Route::get('decks/{deck}/study', [StudentDeckController::class, 'study']);
        Route::put('cards/{card}/progress', [StudentDeckController::class, 'progress']);
        Route::post('decks/{deck}/session-complete', [StudentDeckController::class, 'sessionComplete']);

        Route::get('tests', [TestController::class, 'index']);
        Route::get('tests/{test}', [TestController::class, 'show']);
        Route::post('tests/{test}/attempts', [TestAttemptController::class, 'start']);
        Route::put('attempts/{attempt}/answers', [TestAttemptController::class, 'saveAnswers']);
        Route::post('attempts/{attempt}/submit', [TestAttemptController::class, 'submit']);
        Route::get('attempts/{attempt}/result', [TestAttemptController::class, 'result']);

        Route::middleware('role:teacher,admin')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'index']);

            Route::get('media/class-covers', [MediaController::class, 'classCovers']);
            Route::post('media/upload', [MediaController::class, 'upload']);
            Route::post('media/embed-preview', [MediaController::class, 'embedPreview']);

            // Tài liệu & Bài giảng — khu admin
            Route::get('document-categories', [DocumentCategoryController::class, 'index']);
            Route::put('document-categories/sync', [DocumentCategoryController::class, 'sync']);
            Route::get('storage/usage', [DocumentController::class, 'storageUsage']);
            Route::delete('attachments/bulk', [DocumentAttachmentController::class, 'bulkDelete']);
            Route::delete('attachments/{attachment}', [DocumentAttachmentController::class, 'destroy']);
            Route::post('documents/{document}/attachments', [DocumentAttachmentController::class, 'store']);
            Route::put('documents/{document}/attachments/reorder', [DocumentAttachmentController::class, 'reorder']);
            Route::post('documents/{document}/thumbnail', [DocumentAttachmentController::class, 'thumbnail']);
            Route::patch('documents/{document}/publish', [DocumentController::class, 'publish']);
            Route::apiResource('documents', DocumentController::class);

            // Từ vựng — khu admin (route tĩnh đặt trước {deck})
            Route::get('decks/cards-import-template', [CardController::class, 'importTemplate']);
            Route::get('ipa/lookup', [CardController::class, 'ipaLookup']);
            Route::get('decks', [DeckController::class, 'index']);
            Route::post('decks', [DeckController::class, 'store']);
            Route::get('decks/{deck}', [DeckController::class, 'show']);
            Route::put('decks/{deck}', [DeckController::class, 'update']);
            Route::patch('decks/{deck}/publish', [DeckController::class, 'publish']);
            Route::delete('decks/{deck}', [DeckController::class, 'destroy']);
            Route::post('decks/{deck}/duplicate', [DeckController::class, 'duplicate']);
            Route::get('decks/{deck}/cards', [DeckController::class, 'cards']);
            Route::post('decks/{deck}/cards', [CardController::class, 'store']);
            Route::put('decks/{deck}/cards/reorder', [CardController::class, 'reorder']);
            Route::post('decks/{deck}/cards/import', [CardController::class, 'import']);
            Route::put('cards/{card}', [CardController::class, 'update']);
            Route::delete('cards/{card}', [CardController::class, 'destroy']);
            Route::post('cards/{card}/image', [CardController::class, 'uploadImage']);
            Route::post('cards/{card}/audio', [CardController::class, 'uploadAudio']);
            Route::delete('cards/{card}/audio', [CardController::class, 'deleteAudio']);

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
