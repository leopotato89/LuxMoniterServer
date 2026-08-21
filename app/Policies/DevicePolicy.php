<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

class DevicePolicy
{
    /**
     * Ai cũng xem được danh sách (nhưng bị scope theo chủ sở hữu ở getEloquentQuery).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Device $device): bool
    {
        return $user->isAdmin() || $user->id === $device->owner_id;
    }

    /**
     * Chỉ admin tạo thiết bị trực tiếp; user thêm qua luồng "Thêm thiết bị giám sát" (mã xác minh).
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Device $device): bool
    {
        return $user->isAdmin() || $user->id === $device->owner_id;
    }

    public function delete(User $user, Device $device): bool
    {
        return $user->isAdmin();
    }
}
