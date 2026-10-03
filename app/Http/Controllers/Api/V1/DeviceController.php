<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ClaimDeviceRequest;
use App\Http\Requests\Api\V1\StoreDeviceRequest;
use App\Http\Requests\Api\V1\UpdateDeviceRequest;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use App\Services\MqttService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * CRUD thiết bị + luồng xác minh "Thêm thiết bị giám sát".
 *
 * Quyền truy cập luôn qua DevicePolicy — không tự kiểm tra chủ sở hữu ở đây.
 */
class DeviceController
{
    /**
     * Danh sách thiết bị (phân trang, tìm kiếm, sắp xếp).
     *
     * Admin thấy tất cả; user thường chỉ thấy thiết bị của mình.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Device::class);

        $user = $request->user();
        $search = $request->string('search')->toString();

        $devices = Device::query()
            ->with('owner')
            ->when(! $user->isAdmin(), fn ($query) => $query->where('owner_id', $user->id))
            ->when($search !== '', fn ($query) => $query->where(
                fn ($where) => $where
                    ->where('serial', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%"),
            ))
            ->when($request->has('claimed'), fn ($query) => $request->boolean('claimed')
                ? $query->whereNotNull('owner_id')
                : $query->whereNull('owner_id'))
            ->orderBy($this->sortColumn($request), $this->sortDirection($request))
            ->paginate($this->perPage($request));

        return DeviceResource::collection($devices);
    }

    public function show(Device $device): DeviceResource
    {
        Gate::authorize('view', $device);

        return DeviceResource::make($device->loadMissing('owner'));
    }

    public function store(StoreDeviceRequest $request): JsonResponse
    {
        $device = Device::query()->create($request->validated());

        return DeviceResource::make($device)->response()->setStatusCode(201);
    }

    /**
     * Webhook nội bộ: Worker gọi để tạo thiết bị tự động.
     */
    public function autoRegister(Request $request, MqttService $mqtt): JsonResponse
    {
        $serial = $request->string('serial')->trim()->toString();

        if ($serial === '') {
            return response()->json(['message' => 'Serial is required'], 400);
        }

        $device = Device::firstOrCreate(
            ['serial' => $serial],
            [
                'name' => "Inverter {$serial}",
                'enabled' => true,
            ]
        );

        return response()->json($device);
    }

    /**
     * Webhook nội bộ: Worker gọi để xác nhận ESP32 đã lưu mã thành công, cần clear Retain.
     */
    public function confirmCode(Request $request, MqttService $mqtt): JsonResponse
    {
        $serial = $request->string('serial')->trim()->toString();

        if ($serial === '') {
            return response()->json(['message' => 'Serial is required'], 400);
        }

        // Publish payload rỗng với retain=true để clear bản tin bị kẹt trên Broker
        $mqtt->publish("luxmonitor/{$serial}/cmd/set_code", '', 1, true);

        return response()->json(['status' => 'cleared']);
    }

    public function update(UpdateDeviceRequest $request, Device $device): DeviceResource
    {
        $device->update($request->validated());

        return DeviceResource::make($device);
    }

    public function destroy(Device $device): Response
    {
        Gate::authorize('delete', $device);

        $device->delete();

        return response()->noContent();
    }

    /**
     * Gắn thiết bị vào tài khoản hiện tại sau khi đối chiếu mã xác minh.
     */
    public function claim(ClaimDeviceRequest $request): JsonResponse
    {
        $device = Device::query()
            ->where('serial', $request->string('serial')->trim()->toString())
            ->first();

        $code = $request->string('device_code')->trim()->toString();

        // hash_equals để tránh lộ mã qua thời gian phản hồi.
        if (! $device || ! hash_equals((string) $device->device_code, $code)) {
            return response()->json([
                'message' => 'Mã thiết bị không hợp lệ. Kiểm tra lại serial và mã lấy từ ESP32.',
            ], 422);
        }

        if ($device->owner_id === $request->user()->id) {
            return response()->json([
                'message' => 'Thiết bị đã thuộc tài khoản của bạn.',
            ], 409);
        }

        if ($device->isClaimed()) {
            return response()->json([
                'message' => 'Thiết bị đã thuộc tài khoản khác.',
            ], 409);
        }

        $device->update([
            'owner_id' => $request->user()->id,
            'verified_at' => now(),
        ]);

        return DeviceResource::make($device->loadMissing('owner'))->response()->setStatusCode(201);
    }

    /**
     * Người dùng tự hủy theo dõi thiết bị (bỏ quyền sở hữu).
     */
    public function unclaim(Device $device, Request $request): JsonResponse
    {
        Gate::authorize('update', $device);

        // Đảm bảo chỉ người sở hữu (hoặc admin) mới được huỷ. 
        // Nhưng thường admin sẽ dùng update để đổi chủ, nên cái này chủ yếu cho user.
        $device->update([
            'owner_id' => null,
            'verified_at' => null,
        ]);

        return response()->json(['message' => 'Đã hủy theo dõi thiết bị.']);
    }

    /**
     * Chỉ cho phép sắp xếp theo các cột đã whitelist.
     */
    private function sortColumn(Request $request): string
    {
        $column = $request->string('sort')->toString();

        return in_array($column, ['serial', 'name', 'created_at'], true) ? $column : 'name';
    }

    /**
     * @return 'asc'|'desc'
     */
    private function sortDirection(Request $request): string
    {
        return $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';
    }

    private function perPage(Request $request): int
    {
        return min(100, max(5, $request->integer('per_page', 10)));
    }
}
