<?php

use App\Http\Controllers\RealtimeController;
use Illuminate\Support\Facades\Route;

// Panel user (Filament) đã chiếm đường dẫn gốc "/" — xem app/Providers/Filament/UserPanelProvider.php

// Endpoint realtime cho panel admin (session auth) — trả JSON để Alpine cập nhật số liệu.
Route::get('/admin/devices/{device:serial}/realtime', [RealtimeController::class, 'show'])
    ->middleware('auth');

