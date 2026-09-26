<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ReadDeviceSettingsJob;
use App\Models\Device;
use App\Services\DeviceSettingsService;
use App\Support\InverterSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class DeviceSettingsController extends Controller
{
    public function schema(Device $device): JsonResponse
    {
        Gate::authorize('view', $device);

        return response()->json(InverterSettings::clientSchema());
    }

    public function read(Device $device): JsonResponse
    {
        Gate::authorize('update', $device);

        $jobId = (string) Str::uuid();

        ReadDeviceSettingsJob::dispatch($jobId, $device);

        return response()->json(['job_id' => $jobId], 202);
    }

    public function getReadResult(Device $device, string $jobId): JsonResponse
    {
        Gate::authorize('view', $device);

        $cacheKey = "settings_read:{$jobId}";
        $result = Cache::get($cacheKey);

        if (! $result) {
            return response()->json(['status' => 'pending']);
        }

        return response()->json($result);
    }

    public function updateField(Request $request, Device $device, DeviceSettingsService $service): JsonResponse
    {
        Gate::authorize('update', $device);

        $validated = $request->validate([
            'key' => ['required', 'string'],
            'value' => ['required'], // Có thể là số, bool, string
        ]);

        try {
            $service->saveField($device, $validated['key'], $validated['value'], $request->user());

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function commands(Device $device): JsonResponse
    {
        Gate::authorize('view', $device);

        $commands = $device->commands()->with('user:id,name,email')->latest()->take(50)->get();

        return response()->json($commands);
    }
}
