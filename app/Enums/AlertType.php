<?php

namespace App\Enums;

/**
 * Loại cảnh báo gửi tới người dùng. Giá trị lưu vào `data.type` của bảng notifications.
 */
enum AlertType: string
{
    case LowBattery = 'low_battery';
    case Fault = 'fault';
    case Offline = 'offline';
    case Recovery = 'recovery';
    case GridLost = 'grid_lost';
    case GridRestored = 'grid_restored';

    public function severity(): AlertSeverity
    {
        return match ($this) {
            self::Fault => AlertSeverity::Critical,
            self::LowBattery, self::Offline, self::GridLost => AlertSeverity::Warning,
            self::Recovery, self::GridRestored => AlertSeverity::Info,
        };
    }
}
