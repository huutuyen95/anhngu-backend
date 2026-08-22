<?php

use App\Http\Controllers\Api\AdminAttemptController;
use App\Http\Controllers\Api\AdminTestController;
use App\Http\Controllers\Api\ArticleCategoryController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\ClassroomController;
use App\Http\Controllers\Api\ClassSessionController;
use App\Http\Controllers\Api\ClassStudentController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeckCategoryController;
use App\Http\Controllers\Api\DeckController;
use App\Http\Controllers\Api\DictionaryController;
use App\Http\Controllers\Api\DocumentAttachmentController;
use App\Http\Controllers\Api\DocumentCategoryController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SessionItemController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\StudentArticleController;
use App\Http\Controllers\Api\StudentClassroomController;
use App\Http\Controllers\Api\StudentReportController;
use App\Http\Controllers\Api\StudentSearchController;
use App\Http\Controllers\Api\StudentNotificationController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\StudentDeckController;
use App\Http\Controllers\Api\StudentDocumentController;
use App\Http\Controllers\Api\StudentMissionController;
use App\Http\Controllers\Api\TestAttemptController;
use App\Http\Controllers\Api\TestCategoryController;
use App\Http\Controllers\Api\TestController;
use App\Http\Controllers\Api\TestImportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);

    // Thương hiệu công khai (logo, favicon, tiêu đề, màu) — frontend gọi lúc khởi động.
    Route::get('public/branding', [SettingController::class, 'publicBranding']);

    Route::middleware(['auth:sanctum', 'maintenance'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // Hồ sơ cá nhân (học sinh + giáo viên đều có)
        Route::get('me', [ProfileController::class, 'show']);
        Route::put('me', [ProfileController::class, 'update']);
        Route::post('me/avatar', [ProfileController::class, 'uploadAvatar']);
        Route::delete('me/avatar', [ProfileController::class, 'deleteAvatar']);
        Route::put('me/password', [ProfileController::class, 'updatePassword']);
        Route::post('me/logout-others', [ProfileController::class, 'logoutOthers']);

        // Nhiệm vụ tự đặt — em tự thêm nội dung từ Thư viện, hạn 7 ngày.
        Route::get('me/missions', [StudentMissionController::class, 'index']);
        Route::post('me/missions', [StudentMissionController::class, 'store']);
        Route::delete('me/missions/{mission}', [StudentMissionController::class, 'destroy']);

        // Lớp của em — khu học sinh
        Route::get('me/classrooms', [StudentClassroomController::class, 'index']);
        Route::get('me/report', [StudentReportController::class, 'show']);
        Route::get('me/search', [StudentSearchController::class, 'index']);
        Route::get('me/notifications', [StudentNotificationController::class, 'index']);
        Route::get('me/notifications/unread-count', [StudentNotificationController::class, 'unreadCount']);
        Route::post('me/notifications/read-all', [StudentNotificationController::class, 'markAllRead']);
        Route::post('me/notifications/{id}/read', [StudentNotificationController::class, 'markRead']);
        Route::get('classrooms/{classroom}/roadmap', [StudentClassroomController::class, 'roadmap']);

        // Tài liệu & Bài giảng — khu học sinh
        Route::get('library/documents', [StudentDocumentController::class, 'library']);
        Route::get('documents/{document}/read', [StudentDocumentController::class, 'read']);
        Route::post('documents/{document}/view', [StudentDocumentController::class, 'view']);
        Route::get('dictionary', [DictionaryController::class, 'lookup']);
        Route::post('me/vocab', [DictionaryController::class, 'saveVocab']);

        // Bài viết — thư viện học sinh.
        Route::get('library/articles/categories', [StudentArticleController::class, 'categories']);
        Route::get('library/articles', [StudentArticleController::class, 'index']);
        Route::get('articles/{article}/read', [StudentArticleController::class, 'read']);

        // Từ vựng — khu học sinh
        Route::get('library/decks', [StudentDeckController::class, 'library']);
        Route::get('decks/{deck}/study', [StudentDeckController::class, 'study']);
        Route::put('cards/{card}/progress', [StudentDeckController::class, 'progress']);
        Route::post('decks/{deck}/session-complete', [StudentDeckController::class, 'sessionComplete']);

        Route::get('tests', [TestController::class, 'index']);
        Route::get('tests/{test}', [TestController::class, 'show']);
        Route::post('tests/{test}/attempts', [TestAttemptController::class, 'start']);
        Route::get('attempts/{attempt}', [TestAttemptController::class, 'show']);
        Route::post('attempts/{attempt}/tab-exit', [TestAttemptController::class, 'tabExit']);
        // Đồng hồ chỉ chạy khi học viên đang ở trong màn làm bài.
        Route::post('attempts/{attempt}/pause', [TestAttemptController::class, 'pauseClock']);
        Route::post('attempts/{attempt}/resume', [TestAttemptController::class, 'resumeClock']);
        Route::put('attempts/{attempt}/answers', [TestAttemptController::class, 'saveAnswers']);
        Route::post('attempts/{attempt}/answers/{question}/audio', [TestAttemptController::class, 'uploadAudio']);
        Route::delete('attempts/{attempt}/answers/{question}/audio', [TestAttemptController::class, 'deleteAudio']);
        Route::post('attempts/{attempt}/submit', [TestAttemptController::class, 'submit']);
        Route::get('attempts/{attempt}/result', [TestAttemptController::class, 'result']);

        // Cài đặt hệ thống — CHỈ super admin (không phải mọi admin/teacher).
        Route::middleware(['superadmin', 'token:teacher'])->prefix('admin')->group(function () {
            Route::get('settings', [SettingController::class, 'index']);
            Route::put('settings', [SettingController::class, 'update']);
            Route::post('settings/reset', [SettingController::class, 'reset']);
            Route::post('settings/upload', [SettingController::class, 'upload']);
            Route::delete('settings/file', [SettingController::class, 'deleteFile']);
            Route::get('settings/changes', [SettingController::class, 'changes']);
            Route::post('settings/changes/{change}/revert', [SettingController::class, 'revert']);
            Route::post('settings/mail/test', [SettingController::class, 'mailTest']);
        });

        // role: chốt quyền theo role trong DB (nguồn chuẩn). token:teacher: chặn token
        // học sinh (chỉ có ability 'student') ở cấp phạm vi token — tách bạch 2 loại token.
        Route::middleware(['role:teacher,admin', 'token:teacher'])->group(function () {
            Route::get('dashboard', [DashboardController::class, 'index']);

            Route::get('media/class-covers', [MediaController::class, 'classCovers']);
            Route::post('media/upload', [MediaController::class, 'upload']);
            Route::post('media/embed-preview', [MediaController::class, 'embedPreview']);

            // Bài viết — quản lý riêng với tài liệu/bài giảng.
            Route::get('article-categories', [ArticleCategoryController::class, 'index']);
            Route::put('article-categories/sync', [ArticleCategoryController::class, 'sync']);
            Route::patch('articles/{article}/publish', [ArticleController::class, 'publish']);
            Route::apiResource('articles', ArticleController::class);

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
            Route::get('deck-categories', [DeckCategoryController::class, 'index']);
            Route::put('deck-categories/sync', [DeckCategoryController::class, 'sync']);
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
            Route::patch('cards/{card}/move', [CardController::class, 'move']);
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

            // Đề thi: thư mục (cây theo lớp) + hành động mở rộng + CRUD + editor cấu trúc
            Route::get('admin/test-categories', [TestCategoryController::class, 'index']);
            Route::put('admin/test-categories/sync', [TestCategoryController::class, 'sync']);
            Route::get('admin/tests/word-template', [TestImportController::class, 'template']);
            Route::post('admin/tests/import-word', [TestImportController::class, 'dryRun']);
            Route::post('admin/tests/import-word/commit', [TestImportController::class, 'commit']);
            Route::post('admin/tests/{test}/sections/{section}/audio', [TestImportController::class, 'sectionAudio']);
            Route::post('admin/tests/{test}/duplicate', [AdminTestController::class, 'duplicate']);
            Route::patch('admin/tests/{test}/category', [AdminTestController::class, 'moveCategory']);
            Route::get('admin/tests/{test}/preflight', [AdminTestController::class, 'preflight']);
            Route::put('admin/tests/{test}/structure', [AdminTestController::class, 'saveStructure']);
            Route::apiResource('admin/tests', AdminTestController::class);

            // Chấm bài (chủ yếu writing — cô chấm tay)
            Route::get('admin/attempts', [AdminAttemptController::class, 'index']);
            Route::get('admin/attempts/{attempt}', [AdminAttemptController::class, 'show']);
            Route::post('admin/attempts/{attempt}/grade', [AdminAttemptController::class, 'grade']);

            Route::get('students/check-email', [StudentController::class, 'checkEmail']);
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
