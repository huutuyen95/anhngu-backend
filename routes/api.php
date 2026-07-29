<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClassroomController;
use App\Http\Controllers\Api\DeckController;
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
        Route::post('attempts/{attempt}/submit', [TestAttemptController::class, 'submit']);
        Route::get('attempts/{attempt}/result', [TestAttemptController::class, 'result']);

        Route::middleware('role:teacher,admin')->group(function () {
            Route::get('classrooms', [ClassroomController::class, 'index']);
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
