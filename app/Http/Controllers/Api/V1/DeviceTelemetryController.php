<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Device;
use App\Services\InfluxService;
use App\Services\RealtimeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Telemetry của thiết bị: ảnh chụp realtime (Redis) và lịch sử (InfluxDB).
 */
class DeviceTelemetryController
{
    public function __construct(
        private readonly RealtimeService $realtime,
        private readonly InfluxService $influx,
    ) {}

    /**
     * Ảnh chụp realtime: online + telemetry mới nhất + thời gian bản ghi.
     */
    public function realtime(Device $device): JsonResponse
    {
        Gate::authorize('view', $device);

        return response()->json([
            'data' => $this->realtime->snapshot($device->serial),
        ]);
    }

    /**
     * Chuỗi thời gian theo field và khoảng thời gian.
     */
    public function history(Request $request, Device $device): JsonResponse
    {
        Gate::authorize('view', $device);

        $validated = $request->validate([
            'field' => ['required', 'string'],
            'start' => ['nullable', 'string'],
            'stop' => ['nullable', 'string'],
            'window' => ['nullable', 'string'],
        ]);

        return response()->json([
            'data' => $this->influx->history(
                $device->serial,
                $validated['field'],
                $validated['start'] ?? '-6h',
                $validated['stop'] ?? 'now()',
                $validated['window'] ?? '1m',
            ),
        ]);
    }

    /**
     * Dữ liệu cho biểu đồ tổng hợp nhiều đường (dashboard).
     */
    public function dashboard(Request $request, Device $device): JsonResponse
    {
        Gate::authorize('view', $device);

        $validated = $request->validate([
            'start' => ['nullable', 'string'],
            'stop' => ['nullable', 'string'],
            'window' => ['nullable', 'string'],
        ]);

        return response()->json([
            'data' => $this->influx->dashboard(
                $device->serial,
                $validated['start'] ?? '-24h',
                $validated['stop'] ?? 'now()',
                $validated['window'] ?? '15m',
            ),
        ]);
    }

    /**
     * Dữ liệu thống kê tổng hợp (KPI) cho một ngày (hoặc 1 khoảng).
     */
    public function daily(Request $request, Device $device): JsonResponse
    {
        Gate::authorize('view', $device);

        $validated = $request->validate([
            'date' => ['required', 'string'],
        ]);

        return response()->json([
            'data' => $this->influx->daily(
                $device->serial,
                $validated['date']
            ),
        ]);
    }

    /**
     * Dữ liệu sản lượng theo tháng (Bar Chart).
     */
    public function energy(Request $request, Device $device): JsonResponse
    {
        Gate::authorize('view', $device);

        $validated = $request->validate([
            'type' => ['nullable', 'in:month,year,all'],
            'date' => ['nullable', 'string'],
        ]);

        $type = $validated['type'] ?? 'month';
        $date = $validated['date'] ?? now()->format('Y-m');

        if ($type === 'year') {
            $yearStr = $date;
            $start = Carbon::createFromFormat('Y', $yearStr)->startOfYear()->setTimezone('UTC')->toIso8601ZuluString();
            $stop = Carbon::createFromFormat('Y', $yearStr)->endOfYear()->setTimezone('UTC')->toIso8601ZuluString();
            $data = $this->influx->monthlyEnergy($device->serial, $start, $stop);
        } elseif ($type === 'all') {
            $data = $this->influx->yearlyEnergy($device->serial, '2020-01-01T00:00:00Z', 'now()');
        } else {
            $monthStr = $date;
            $start = Carbon::createFromFormat('Y-m', $monthStr)->startOfMonth()->setTimezone('UTC')->toIso8601ZuluString();
            $stop = Carbon::createFromFormat('Y-m', $monthStr)->endOfMonth()->setTimezone('UTC')->toIso8601ZuluString();
            $data = $this->influx->dailyEnergy($device->serial, $start, $stop);
        }

        return response()->json(['data' => $data]);
    }
}
