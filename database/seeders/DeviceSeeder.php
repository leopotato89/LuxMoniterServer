<?php

namespace Database\Seeders;

use App\Models\Device;
use Illuminate\Database\Seeder;

class DeviceSeeder extends Seeder
{
    /**
     * Thiết bị demo (chưa gắn chủ để test luồng xác minh).
     * Mã thiết bị demo: DEMO2026
     */
    public function run(): void
    {
        Device::updateOrCreate(
            ['serial' => '4313800597'],
            [
                'name' => 'Inverter LuxPower',
                'owner_id' => null,
                'device_code' => 'DEMO2026',
                'verified_at' => null,
                'enabled' => true,
            ],
        );
    }
}
