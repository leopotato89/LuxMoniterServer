<?php

namespace App\Services;

use App\Enums\AlertType;
use App\Models\Device;
use App\Models\DeviceAlertSetting;
use App\Models\User;
use App\Notifications\DeviceAlertNotification;
use Illuminate\Support\Facades\Cache;

/**
 * Đọc trạng thái mới nhất của thiết bị (Redis) và gửi cảnh báo cho chủ sở hữu:
 *  - Mất kết nối / Online lại
 *  - Biến tần báo lỗi / Hết lỗi
 *  - Mất điện lưới / Có điện lưới trở lại
 *  - Pin yếu theo 2 cơ chế (SOC hoặc Voltage), theo nấc 5% hoặc 0.2V, cooldown 1 tiếng cho cùng mức.
 */
class AlertDetectionService
{
    /** Giây tối thiểu giữa hai cảnh báo cùng loại (offline, fault, grid). */
    public const COOLDOWN = 900;

    /** Giây tối thiểu không báo lại ở cùng mức VOL hoặc SOC (1 tiếng). */
    public const BATTERY_STEP_COOLDOWN = 3600;

    /** Ngưỡng hồi phục SOC (ngưỡng cài đặt + 5%). */
    public const HYSTERESIS_SOC = 5;

    /** Ngưỡng hồi phục Điện áp (ngưỡng cài đặt + 0.5V). */
    public const HYSTERESIS_VOLT = 0.5;

    /** Mã `state` của biến tần ứng với Fault (xem EnergyDiagram.vue). */
    private const STATE_FAULT = 1;

    public function __construct(
        private readonly RealtimeService $realtime,
    ) {}

    public function check(Device $device): void
    {
        $owner = $device->owner;

        if ($owner === null || ! $device->enabled) {
            return;
        }

        $setting = $device->alertSettingOrDefault();
        $name = $device->name ?: $device->serial;
        $online = $this->realtime->online($device->serial);

        $this->checkOffline($device, $owner, $setting, $name, $online);

        $latest = $this->realtime->latest($device->serial);

        if ($latest === null) {
            return;
        }

        $this->checkFault($device, $owner, $setting, $name, $latest);
        $this->checkGrid($device, $owner, $setting, $name, $online, $latest);
        $this->checkBattery($device, $owner, $setting, $name, $latest);
    }

    private function checkOffline(
        Device $device,
        User $owner,
        DeviceAlertSetting $setting,
        string $name,
        bool $online,
    ): void {
        $this->track(
            $device,
            $owner,
            AlertType::Offline,
            triggered: $setting->notify_offline && ! $online,
            cleared: $online,
            title: 'Mất kết nối thiết bị',
            body: "Thiết bị LuxMonitor {$name} đã mất kết nối Internet.",
            recoveredBody: "Thiết bị {$name} đã online trở lại.",
            notifyRecovery: $setting->notify_recovery,
        );
    }

    /**
     * @param  array<string, mixed>  $latest
     */
    private function checkFault(
        Device $device,
        User $owner,
        DeviceAlertSetting $setting,
        string $name,
        array $latest,
    ): void {
        $faulty = (int) ($latest['state'] ?? 0) === self::STATE_FAULT
            || (int) ($latest['fault_code'] ?? 0) !== 0
            || (int) ($latest['warning_code'] ?? 0) !== 0;

        $this->track(
            $device,
            $owner,
            AlertType::Fault,
            triggered: $setting->notify_fault && $faulty,
            cleared: ! $faulty,
            title: 'Biến tần báo lỗi',
            body: "Biến tần {$name} đang báo lỗi. Hãy kiểm tra thiết bị.",
            recoveredBody: "Biến tần {$name} đã hết lỗi.",
            notifyRecovery: $setting->notify_recovery,
        );
    }

    /**
     * Cảnh báo mất điện lưới (< 50V) và có điện lưới trở lại (>= 180V).
     *
     * @param  array<string, mixed>  $latest
     */
    private function checkGrid(
        Device $device,
        User $owner,
        DeviceAlertSetting $setting,
        string $name,
        bool $online,
        array $latest,
    ): void {
        if (! $setting->notify_grid || ! $online) {
            return;
        }

        $gridVoltage = (float) ($latest['grid_voltage'] ?? 0);
        $key = "alert:{$device->serial}:grid";

        $gridLost = $gridVoltage < 50.0;
        $gridRestored = $gridVoltage >= 180.0;

        if (Cache::has("{$key}:active")) {
            if ($gridRestored) {
                Cache::forget("{$key}:active");

                if ($setting->notify_recovery) {
                    $this->send(
                        $owner,
                        AlertType::GridRestored,
                        $device,
                        'Đã có điện lưới',
                        sprintf('Biến tần %s đã có điện lưới trở lại (%.1f V).', $name, $gridVoltage)
                    );
                }
            }

            return;
        }

        if ($gridLost) {
            $send = Cache::add("{$key}:cooldown", true, self::COOLDOWN);
            Cache::forever("{$key}:active", true);

            if ($send) {
                $this->send(
                    $owner,
                    AlertType::GridLost,
                    $device,
                    'Mất điện lưới',
                    "Biến tần {$name} phát hiện mất điện lưới. Hệ thống đang chuyển sang dùng pin lưu trữ/mặt trời."
                );
            }
        }
    }

    /**
     * Cảnh báo pin yếu theo nấc (5% hoặc 0.2V) khi đang xả; không lặp lại cùng mức trong 1 tiếng.
     *
     * @param  array<string, mixed>  $latest
     */
    private function checkBattery(
        Device $device,
        User $owner,
        DeviceAlertSetting $setting,
        string $name,
        array $latest,
    ): void {
        $discharging = (float) ($latest['discharge_power'] ?? 0) > 0;
        $mode = $setting->battery_alert_mode;
        $activeKey = "alert:{$device->serial}:battery:active";

        if ($mode === 'voltage') {
            $voltage = (float) ($latest['battery_voltage'] ?? 0);

            if ($voltage <= 0) {
                return;
            }

            // Hồi phục: điện áp tăng lên trên ngưỡng + 0.5V
            if ($voltage >= $setting->low_battery_voltage + self::HYSTERESIS_VOLT) {
                if (Cache::pull($activeKey) && $setting->notify_recovery) {
                    $this->send(
                        $owner,
                        AlertType::Recovery,
                        $device,
                        'Pin đã hồi phục',
                        sprintf('Điện áp pin của %s đã hồi phục lên %.1f Vol.', $name, $voltage)
                    );
                }

                return;
            }

            // Pin yếu khi đang xả và điện áp <= ngưỡng
            if ($discharging && $voltage <= $setting->low_battery_voltage) {
                // Tính nấc 0.2V (ví dụ: 52.2, 52.0, 51.8...)
                $level = round(floor(($voltage + 0.0001) / 0.2) * 0.2, 1);
                $stepKey = "alert:{$device->serial}:battery:step:v:{$level}";

                // Không báo lại cùng mức trong 1 tiếng
                if (Cache::add($stepKey, true, self::BATTERY_STEP_COOLDOWN)) {
                    Cache::forever($activeKey, true);

                    $this->send(
                        $owner,
                        AlertType::LowBattery,
                        $device,
                        'Cảnh báo điện áp pin thấp',
                        sprintf('Điện áp pin đang ở mức thấp (%.1f Vol). Hãy chú ý tải tiêu thụ.', $voltage)
                    );
                }
            }

            return;
        }

        // Mode mặc định: SOC (%)
        $soc = (float) ($latest['battery_soc'] ?? 100);

        // Hồi phục: SOC tăng lên trên ngưỡng + 5%
        if ($soc >= $setting->low_battery_soc + self::HYSTERESIS_SOC) {
            if (Cache::pull($activeKey) && $setting->notify_recovery) {
                $this->send(
                    $owner,
                    AlertType::Recovery,
                    $device,
                    'Pin đã hồi phục',
                    sprintf('Pin của %s đã sạc lại lên %d%%.', $name, (int) $soc)
                );
            }

            return;
        }

        // Pin yếu khi đang xả và SOC <= ngưỡng
        if ($discharging && $soc <= $setting->low_battery_soc) {
            // Tính nấc 5% (ví dụ: 30, 25, 20, 15, 10, 5...)
            $level = (int) (floor($soc / 5.0) * 5);
            $stepKey = "alert:{$device->serial}:battery:step:s:{$level}";

            // Không báo lại cùng mức trong 1 tiếng
            if (Cache::add($stepKey, true, self::BATTERY_STEP_COOLDOWN)) {
                Cache::forever($activeKey, true);

                $this->send(
                    $owner,
                    AlertType::LowBattery,
                    $device,
                    'Cảnh báo dung lượng pin thấp',
                    sprintf('Pin lưu trữ còn lại %d%%. Hãy chú ý tải tiêu thụ.', (int) $soc)
                );
            }
        }
    }

    private function track(
        Device $device,
        User $owner,
        AlertType $type,
        bool $triggered,
        bool $cleared,
        string $title,
        string $body,
        string $recoveredBody,
        bool $notifyRecovery,
    ): void {
        $key = "alert:{$device->serial}:{$type->value}";

        if (Cache::has("{$key}:active")) {
            if ($cleared) {
                $wasAlerted = Cache::pull("{$key}:active") === true;

                if ($wasAlerted && $notifyRecovery) {
                    $this->send($owner, AlertType::Recovery, $device, 'Đã khôi phục bình thường', $recoveredBody);
                }
            }

            return;
        }

        if (! $triggered) {
            return;
        }

        $send = Cache::add("{$key}:cooldown", true, self::COOLDOWN);

        Cache::forever("{$key}:active", $send);

        if ($send) {
            $this->send($owner, $type, $device, $title, $body);
        }
    }

    private function send(User $owner, AlertType $type, Device $device, string $title, string $body): void
    {
        $owner->notify(new DeviceAlertNotification($type, $device->serial, $title, $body));
    }
}
