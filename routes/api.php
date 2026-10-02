<?php

use App\Http\Controllers\Api\DeviceCodeController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\DeviceSettingsController;
use App\Http\Controllers\Api\V1\DeviceTelemetryController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Điểm cuối cho ESP32 / client ngoài.
*/

// Công khai: ESP32 gọi để lấy mã thiết bị (cân nhắc bảo mật chi tiết ở Phase 6)
Route::get('/device-code/{serial}', DeviceCodeController::class);

/*
|--------------------------------------------------------------------------
| v1 — API dùng chung cho SPA và mobile (Sanctum Bearer token)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function (): void {
    // Công khai
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:login');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:login');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:login');

    // Cần token và phải đang hoạt động
    Route::middleware(['auth:sanctum', EnsureUserIsActive::class])->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::put('/auth/password', [AuthController::class, 'changePassword']);

        // Thiết bị. `claim` phải đứng trước `{device}` để không bị bind nhầm.
        Route::post('/devices/claim', [DeviceController::class, 'claim']);
        Route::get('/devices', [DeviceController::class, 'index']);
        Route::post('/devices', [DeviceController::class, 'store']);
        Route::get('/devices/{device:serial}', [DeviceController::class, 'show']);
        Route::patch('/devices/{device:serial}', [DeviceController::class, 'update']);
        Route::delete('/devices/{device:serial}', [DeviceController::class, 'destroy']);

        // Telemetry
        Route::get('/devices/{device:serial}/realtime', [DeviceTelemetryController::class, 'realtime']);
        Route::get('/devices/{device:serial}/history', [DeviceTelemetryController::class, 'history']);
        Route::get('/devices/{device:serial}/dashboard', [DeviceTelemetryController::class, 'dashboard']);
        Route::get('/devices/{device:serial}/daily', [DeviceTelemetryController::class, 'daily']);
        Route::get('/devices/{device:serial}/energy', [DeviceTelemetryController::class, 'energy']);

        // Settings
        Route::get('/devices/{device:serial}/settings/schema', [DeviceSettingsController::class, 'schema']);
        Route::post('/devices/{device:serial}/settings/read', [DeviceSettingsController::class, 'read']);
        Route::get('/devices/{device:serial}/settings/read/{jobId}', [DeviceSettingsController::class, 'getReadResult']);
        Route::put('/devices/{device:serial}/settings/field', [DeviceSettingsController::class, 'updateField']);
        Route::get('/devices/{device:serial}/commands', [DeviceSettingsController::class, 'commands']);

        // Users (Admin)
        Route::apiResource('users', UserController::class);
        Route::post('/users/{user}/toggle-active', [UserController::class, 'toggleActive']);
    });
});
