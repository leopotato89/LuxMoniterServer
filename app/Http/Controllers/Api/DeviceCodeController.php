<?php

namespace App\Http\Controllers\Api;

use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class DeviceCodeController
{
    /**
     * Cấp (hoặc lấy lại) mã thiết bị cho một serial.
     * ESP32 gọi endpoint này để lấy mã xác minh.
     */
    public function __invoke(string $serial): JsonResponse
    {
        $serial = trim($serial);

        if ($serial === '') {
            return response()->json(['error' => 'Thiếu serial'], 422);
        }

        $device = Device::firstOrCreate(
            ['serial' => $serial],
            ['name' => "Thiết bị $serial"],
        );

        if (blank($device->device_code)) {
            $device->device_code = strtoupper(Str::random(8));
            $device->save();
        }

        return response()->json([
            'serial' => $device->serial,
            'device_code' => $device->device_code,
        ]);
    }
}
