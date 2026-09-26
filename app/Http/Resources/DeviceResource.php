<?php

namespace App\Http\Resources;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Device
 */
class DeviceResource extends JsonResource
{
    /**
     * `device_code` là mã xác minh — chỉ chủ sở hữu hoặc admin được thấy.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $canSeeCode = $user !== null && ($user->is_admin || $user->id === $this->owner_id);

        return [
            'serial' => $this->serial,
            'name' => $this->name,
            'enabled' => (bool) $this->enabled,
            'is_claimed' => $this->owner_id !== null,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'device_code' => $this->when($canSeeCode, $this->device_code),
            'owner' => UserResource::make($this->whenLoaded('owner')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
