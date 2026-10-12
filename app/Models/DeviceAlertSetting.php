<?php

namespace App\Models;

use Database\Factories\DeviceAlertSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $device_id
 * @property string $battery_alert_mode
 * @property int $low_battery_soc
 * @property float $low_battery_voltage
 * @property bool $notify_fault
 * @property bool $notify_grid
 * @property bool $notify_offline
 * @property bool $notify_recovery
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'device_id',
    'battery_alert_mode',
    'low_battery_soc',
    'low_battery_voltage',
    'notify_fault',
    'notify_grid',
    'notify_offline',
    'notify_recovery',
])]
class DeviceAlertSetting extends Model
{
    /** @use HasFactory<DeviceAlertSettingFactory> */
    use HasFactory;

    /** Giá trị mặc định khi thiết bị chưa có bản ghi cấu hình. */
    public const DEFAULTS = [
        'battery_alert_mode' => 'soc',
        'low_battery_soc' => 20,
        'low_battery_voltage' => 48.0,
        'notify_fault' => true,
        'notify_grid' => true,
        'notify_offline' => true,
        'notify_recovery' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'low_battery_soc' => 'integer',
            'low_battery_voltage' => 'float',
            'notify_fault' => 'boolean',
            'notify_grid' => 'boolean',
            'notify_offline' => 'boolean',
            'notify_recovery' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
