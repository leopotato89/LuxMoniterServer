<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;

/**
 * Đọc trạng thái MỚI NHẤT của thiết bị từ Redis
 * (do worker Node.js ghi: device:<serial>:latest / device:<serial>:online).
 */
class RealtimeService
{
    public function __construct(
        private readonly InfluxService $influx,
    ) {}

    /**
     * Ảnh chụp trạng thái realtime của thiết bị — nguồn duy nhất cho SPA và mobile.
     *
     * @return array{online: bool, latest: array<string, mixed>|null, timestamp: string|null}
     */
    public function snapshot(string $serial): array
    {
        $latest = $this->latest($serial);

        return [
            'online' => $this->online($serial),
            'latest' => $latest,
            'timestamp' => $this->recordTime($serial, $latest),
        ];
    }

    /**
     * Thời gian của bản ghi realtime đang hiển thị.
     *
     * - Nếu worker ghi field `time` (hoặc `timestamp`) trong telemetry Redis → dùng luôn (gốc rễ).
     * - Nếu không, lấy `_time` mới nhất từ InfluxDB.
     * - Cuối cùng, fallback server time.
     *
     * @param  array<string, mixed>|null  $latest
     */
    private function recordTime(string $serial, ?array $latest): ?string
    {
        if ($latest) {
            $raw = $latest['time'] ?? $latest['timestamp'] ?? null;

            if ($raw) {
                return Carbon::parse($raw)->setTimezone(config('app.timezone'))->format('H:i:s d/m/Y');
            }
        }

        return $this->influx->latestTime($serial)?->format('H:i:s d/m/Y')
            ?? ($latest ? now()->format('H:i:s d/m/Y') : null);
    }

    /**
     * Telemetry JSON mới nhất của thiết bị (hoặc null nếu chưa có).
     *
     * @return array<string, mixed>|null
     */
    public function latest(string $serial): ?array
    {
        $json = Redis::get("device:{$serial}:latest");

        if (! $json) {
            return null;
        }

        $data = json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Thiết bị có online không (dựa trên Redis device:<serial>:online).
     */
    public function online(string $serial): bool
    {
        return Redis::get("device:{$serial}:online") === '1';
    }

    /**
     * Kết quả lệnh (đọc/ghi cài đặt) mới nhất của thiết bị (JSON string, hoặc null).
     * Do worker ghi từ topic `luxmonitor/<serial>/cmd/result`.
     */
    public function commandResult(string $serial): ?string
    {
        $value = Redis::get("device:{$serial}:cmd_result");

        return $value ?: null;
    }

    /**
     * Xóa kết quả lệnh cũ trước khi gửi lệnh mới (tránh khớp nhầm).
     */
    public function clearCommandResult(string $serial): void
    {
        Redis::del("device:{$serial}:cmd_result");
    }
}
