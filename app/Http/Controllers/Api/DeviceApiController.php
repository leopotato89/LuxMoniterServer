<?php

namespace App\Http\Controllers\Api;

use App\Models\Device;
use App\Services\InfluxService;
use App\Services\RealtimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceApiController
{
    public function __construct(
        private readonly RealtimeService $realtime,
        private readonly InfluxService $influx,
    ) {}

    /**
     * Danh sách thiết bị mà user hiện tại có quyền xem.
     */
    public function index(): JsonResponse
    {
        $devices = Device::query()
            ->when(
                ! auth()->user()->isAdmin(),
                fn ($query) => $query->where('owner_id', auth()->id()),
            )
            ->orderBy('name')
            ->get(['serial', 'name', 'owner_id', 'enabled']);

        return response()->json(['data' => $devices]);
    }

    /**
     * Telemetry mới nhất (Redis).
     */
    public function latest(string $serial): JsonResponse
    {
        if (! $this->canAccess($serial)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json([
            'data' => $this->realtime->latest($serial),
        ]);
    }

    /**
     * Trạng thái online/offline (Redis).
     */
    public function status(string $serial): JsonResponse
    {
        if (! $this->canAccess($serial)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json([
            'data' => [
                'serial' => $serial,
                'online' => $this->realtime->online($serial),
            ],
        ]);
    }

    /**
     * Lịch sử time-series (InfluxDB) theo field/khoảng thời gian/window.
     */
    public function history(Request $request, string $serial): JsonResponse
    {
        if (! $this->canAccess($serial)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'field' => ['required', 'string'],
            'start' => ['nullable', 'string'],
            'stop' => ['nullable', 'string'],
            'window' => ['nullable', 'string'],
        ]);

        $points = $this->influx->history(
            $serial,
            $validated['field'],
            $validated['start'] ?? '-6h',
            $validated['stop'] ?? 'now()',
            $validated['window'] ?? '1m',
        );

        return response()->json(['data' => $points]);
    }

    /**
     * Chỉ chủ sở hữu hoặc admin được truy cập thiết bị.
     */
    protected function canAccess(string $serial): bool
    {
        $device = Device::query()->where('serial', $serial)->first();

        return $device !== null
            && (auth()->user()->isAdmin() || $device->owner_id === auth()->id());
    }
}
