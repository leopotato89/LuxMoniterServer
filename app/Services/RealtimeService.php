<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

/**
 * Đọc trạng thái MỚI NHẤT của thiết bị từ Redis
 * (do worker Node.js ghi: device:<serial>:latest / device:<serial>:online).
 */
class RealtimeService
{
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
