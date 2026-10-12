<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Device;
use App\Models\DeviceAlertSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Ngưỡng cảnh báo của từng thiết bị. Quyền truy cập qua DevicePolicy.
 */
class DeviceAlertSettingController
{
    public function show(Device $device): JsonResponse
    {
        Gate::authorize('view', $device);

        return response()->json(['data' => $this->payload($device->alertSettingOrDefault())]);
    }

    public function update(Request $request, Device $device): JsonResponse
    {
        Gate::authorize('update', $device);

        $data = $request->validate([
            'battery_alert_mode' => ['sometimes', 'string', 'in:soc,voltage'],
            'low_battery_soc' => ['sometimes', 'integer', 'min:5', 'max:50'],
            'low_battery_voltage' => ['sometimes', 'numeric', 'min:35', 'max:60'],
            'notify_fault' => ['sometimes', 'boolean'],
            'notify_grid' => ['sometimes', 'boolean'],
            'notify_offline' => ['sometimes', 'boolean'],
            'notify_recovery' => ['sometimes', 'boolean'],
        ]);

        $setting = $device->alertSetting()->updateOrCreate([], $data);

        return response()->json(['data' => $this->payload($setting->refresh())]);
    }

    /**
     * @return array<string, int|float|bool|string>
     */
    private function payload(DeviceAlertSetting $setting): array
    {
        return [
            'battery_alert_mode' => $setting->battery_alert_mode,
            'low_battery_soc' => $setting->low_battery_soc,
            'low_battery_voltage' => $setting->low_battery_voltage,
            'notify_fault' => $setting->notify_fault,
            'notify_grid' => $setting->notify_grid,
            'notify_offline' => $setting->notify_offline,
            'notify_recovery' => $setting->notify_recovery,
        ];
    }
}
