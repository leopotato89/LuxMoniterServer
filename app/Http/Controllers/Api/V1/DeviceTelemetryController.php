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
                $validated['window'] ?? '5m',
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
        $date = $validated['date'] ?? null;

        $tz = config('app.timezone');
        $now = Carbon::now($tz);
        $todayStr = $now->format('Y-m-d');
        $thisMonthStr = $now->format('Y-m');
        $thisYearStr = $now->format('Y');

        if ($type === 'year') {
            $yearStr = ! empty($date) ? substr($date, 0, 4) : $thisYearStr;
            $yearCarbon = Carbon::createFromDate((int) $yearStr, 1, 1, $tz)->startOfYear();

            if ($yearCarbon->isAfter($now)) {
                return response()->json(['data' => []]);
            }

            $start = $yearCarbon->copy()->setTimezone('UTC')->toIso8601ZuluString();
            $stopCarbon = $yearCarbon->copy()->addYear();
            if ($stopCarbon->isAfter($now)) {
                $stopCarbon = $now;
            }
            $stop = $stopCarbon->copy()->setTimezone('UTC')->toIso8601ZuluString();

            $data = $this->influx->monthlyEnergy($device->serial, $start, $stop);

            $data = array_filter(
                $data,
                fn (string $key): bool => $key <= $thisMonthStr,
                ARRAY_FILTER_USE_KEY
            );
        } elseif ($type === 'all') {
            $start = '2020-01-01T00:00:00Z';
            $stop = $now->copy()->setTimezone('UTC')->toIso8601ZuluString();

            $data = $this->influx->yearlyEnergy($device->serial, $start, $stop);

            $data = array_filter(
                $data,
                fn (string $key): bool => $key <= $thisYearStr,
                ARRAY_FILTER_USE_KEY
            );
        } else {
            $monthStr = ! empty($date) ? substr($date, 0, 7) : $thisMonthStr;
            $monthCarbon = Carbon::parse($monthStr.'-01', $tz)->startOfMonth();

            if ($monthCarbon->isAfter($now)) {
                return response()->json(['data' => []]);
            }

            $start = $monthCarbon->copy()->setTimezone('UTC')->toIso8601ZuluString();
            $stopCarbon = $monthCarbon->copy()->addMonth();
            if ($stopCarbon->isAfter($now)) {
                $stopCarbon = $now;
            }
            $stop = $stopCarbon->copy()->setTimezone('UTC')->toIso8601ZuluString();

            $data = $this->influx->dailyEnergy($device->serial, $start, $stop);

            $data = array_filter(
                $data,
                fn (string $key): bool => $key <= $todayStr,
                ARRAY_FILTER_USE_KEY
            );
        }

        return response()->json(['data' => $data]);
    }
}
