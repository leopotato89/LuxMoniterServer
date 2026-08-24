<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\InfluxService;
use App\Services\RealtimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Endpoint trả telemetry realtime (JSON) cho panel admin — dùng session auth
 * (không cần Sanctum token), phục vụ frontend cập nhật số liệu bằng JS mà
 * không re-render lại toàn bộ HTML.
 */
class RealtimeController extends Controller
{
    public function __construct(
        private readonly RealtimeService $realtime,
    ) {}

    public function show(Device $device): JsonResponse
    {
        $latest = $this->realtime->latest($device->serial);

        return response()->json([
            'online' => $this->realtime->online($device->serial),
            'latest' => $latest,
            // Ưu tiên thời gian bản ghi (worker ghi field `time` vào Redis),
            // fallback sang `_time` InfluxDB, cuối cùng là server time.
            'timestamp' => $this->recordTime($latest, $device->serial),
        ]);
    }

    /**
     * Thời gian của bản ghi realtime đang hiển thị.
     *
     * - Nếu worker ghi field `time` (hoặc `timestamp`) trong telemetry Redis → dùng luôn (gốc rễ).
     * - Nếu không, lấy `_time` mới nhất từ InfluxDB.
     * - Cuối cùng, fallback server time.
     */
    private function recordTime(?array $latest, string $serial): ?string
    {
        if ($latest) {
            $raw = $latest['time'] ?? $latest['timestamp'] ?? null;

            if ($raw) {
                return Carbon::parse($raw)->setTimezone(config('app.timezone'))->format('H:i:s d/m/Y');
            }
        }

        return app(InfluxService::class)->latestTime($serial)?->format('H:i:s d/m/Y')
            ?? ($latest ? now()->format('H:i:s d/m/Y') : null);
    }
}
