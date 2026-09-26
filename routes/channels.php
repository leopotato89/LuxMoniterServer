<?php

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Kênh broadcast
|--------------------------------------------------------------------------
| Kênh riêng của từng thiết bị. Quyền xem dùng lại DevicePolicy (chủ sở hữu
| hoặc admin) — cùng một nguồn sự thật với API, nên không lặp lại lỗi IDOR
| của RealtimeController cũ.
*/

Broadcast::channel('device.{serial}', function (User $user, string $serial): bool {
    $device = Device::query()->where('serial', $serial)->first();

    return $device !== null && $user->can('view', $device);
});
