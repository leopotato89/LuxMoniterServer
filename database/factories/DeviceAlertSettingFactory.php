<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\DeviceAlertSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceAlertSetting>
 */
class DeviceAlertSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => Device::factory(),
            ...DeviceAlertSetting::DEFAULTS,
        ];
    }
}
