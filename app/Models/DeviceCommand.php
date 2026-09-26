<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceCommand extends Model
{
    protected $fillable = [
        'device_id',
        'user_id',
        'reg',
        'bit',
        'value',
        'request_json',
        'status',
        'ok',
        'result_json',
        'error',
    ];

    protected $casts = [
        'reg' => 'integer',
        'bit' => 'integer',
        'value' => 'integer',
        'ok' => 'boolean',
    ];

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
