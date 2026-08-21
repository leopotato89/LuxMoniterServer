<?php

use App\Http\Controllers\Api\DeviceApiController;
use App\Http\Controllers\Api\DeviceCodeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Điểm cuối cho ESP32 / client ngoài.
*/

// Công khai: ESP32 gọi để lấy mã thiết bị (cân nhắc bảo mật chi tiết ở Phase 6)
Route::get('/device-code/{serial}', DeviceCodeController::class);

// Cần token Sanctum
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/devices', [DeviceApiController::class, 'index']);
    Route::get('/devices/{serial}/latest', [DeviceApiController::class, 'latest']);
    Route::get('/devices/{serial}/status', [DeviceApiController::class, 'status']);
    Route::get('/devices/{serial}/history', [DeviceApiController::class, 'history']);
});
