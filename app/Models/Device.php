<?php

namespace App\Models;

use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $serial
 * @property string|null $name
 * @property int|null $owner_id
 * @property string|null $device_code
 * @property Carbon|null $verified_at
 * @property bool $enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['serial', 'name', 'owner_id', 'device_code', 'verified_at', 'enabled'])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'enabled' => 'boolean',
        ];
    }

    /**
     * Chủ sở hữu thiết bị (nullable = chưa được claim).
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Nhật ký các lệnh cài đặt đã gửi tới thiết bị (audit).
     */
    public function commands(): HasMany
    {
        return $this->hasMany(DeviceCommand::class);
    }

    /**
     * Thiết bị đã được gắn về một người dùng chưa.
     */
    public function isClaimed(): bool
    {
        return $this->owner_id !== null;
    }
}
