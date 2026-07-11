<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeckController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('decks', [DeckController::class, 'index']);
        Route::get('decks/{deck}', [DeckController::class, 'show']);
        Route::post('decks/{deck}/progress', [DeckController::class, 'saveProgress']);

        Route::middleware('role:teacher,admin')->prefix('teacher')->group(function () {
            //
        });
    });
});
