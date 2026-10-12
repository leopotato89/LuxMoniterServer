<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\AlertDetectionService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Quét các thiết bị đã có chủ và gửi cảnh báo (pin yếu, lỗi, mất kết nối).
 *
 * Chạy nền như `telemetry:broadcast`; một thiết bị lỗi không làm dừng cả vòng quét.
 */
class DetectDeviceAlerts extends Command
{
    protected $signature = 'telemetry:alerts
        {--once : Quét một lượt rồi thoát}
        {--interval=10 : Số giây giữa hai lượt quét}';

    protected $description = 'Phát hiện cảnh báo thiết bị và gửi thông báo cho chủ sở hữu';

    public function handle(AlertDetectionService $alerts): int
    {
        $interval = max(1, (int) $this->option('interval'));

        while (true) {
            Device::query()
                ->whereNotNull('owner_id')
                ->where('enabled', true)
                ->with(['owner', 'alertSetting'])
                ->each(function (Device $device) use ($alerts): void {
                    try {
                        $alerts->check($device);
                    } catch (Throwable $e) {
                        $this->error("{$device->serial}: {$e->getMessage()}");
                    }
                });

            if ($this->option('once')) {
                return self::SUCCESS;
            }

            sleep($interval);
        }
    }
}
