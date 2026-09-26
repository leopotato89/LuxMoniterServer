<?php

namespace App\Jobs;

use App\Models\Device;
use App\Services\DeviceSettingsService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ReadDeviceSettingsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 40;

    public function __construct(
        public readonly string $jobId,
        public readonly Device $device,
    ) {}

    public function uniqueId(): string
    {
        return $this->device->serial;
    }

    public function handle(DeviceSettingsService $settingsService): void
    {
        $cacheKey = "settings_read:{$this->jobId}";

        try {
            // Read settings, blocking up to 25s
            $values = $settingsService->read($this->device);
            Cache::put($cacheKey, [
                'status' => 'success',
                'values' => $values,
            ], now()->addMinutes(5));
        } catch (Throwable $e) {
            Cache::put($cacheKey, [
                'status' => 'error',
                'error' => $e->getMessage(),
            ], now()->addMinutes(5));
        }
    }
}
