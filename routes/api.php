<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\UserController;

Route::apiResource('books', BookController::class)->only(['index', 'show'])
    ->middleware(['throttle:read']);

Route::apiResource('books', BookController::class)->only(['update', 'store', 'destroy'])
    ->middleware(['auth:sanctum', 'throttle:write']);

Route::apiResource('users', UserController::class)->only(['update', 'store', 'destroy'])
    ->middleware(['auth:sanctum', 'throttle:write']);

Route::apiResource('users', UserController::class)->only(['index', 'show'])
    ->middleware(['auth:sanctum', 'throttle:read']);

Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:auth');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:auth');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/loans', [LoanController::class, 'index'])
        ->middleware('throttle:read');

    Route::get('/loans/{loan}', [LoanController::class, 'show'])
        ->middleware('throttle:read');

    Route::post('/loans', [LoanController::class, 'store'])
        ->middleware('throttle:loans');   // rate limit khusus, per-anggota (NFR)

    Route::post('/loans/{loan}/return', [LoanController::class, 'returnBook'])
        ->middleware('throttle:write');
    
    Route::get('/loans/me', [LoanController::class, 'mine']);
    Route::get('/loans/{loan}', [LoanController::class, 'show'])->whereNumber('loan');
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');