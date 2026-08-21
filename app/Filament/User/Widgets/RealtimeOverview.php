<?php

namespace App\Filament\User\Widgets;

use App\Models\Device;
use App\Services\RealtimeService;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class RealtimeOverview extends Widget
{
    protected string $view = 'filament.user.widgets.realtime-overview';

    /**
     * Poll mỗi 3 giây để cập nhật số liệu realtime từ Redis.
     */
    protected int|string|array $pollingInterval = '3s';

    public ?string $deviceSerial = null;

    public function mount(): void
    {
        $this->deviceSerial ??= $this->getDevices()->first()?->serial;
    }

    /**
     * Các thiết bị mà user hiện tại có quyền xem (theo panel).
     */
    public function getDevices(): Collection
    {
        $query = Device::query();

        if (Filament::getCurrentPanel()?->getId() === 'user' || ! auth()->user()->isAdmin()) {
            $query->where('owner_id', auth()->id());
        }

        return $query->orderBy('name')->get(['serial', 'name']);
    }

    /**
     * Telemetry mới nhất từ Redis.
     *
     * @return array<string, mixed>|null
     */
    public function getLatest(): ?array
    {
        if (! $this->deviceSerial) {
            return null;
        }

        return app(RealtimeService::class)->latest($this->deviceSerial);
    }

    public function isOnline(): bool
    {
        if (! $this->deviceSerial) {
            return false;
        }

        return app(RealtimeService::class)->online($this->deviceSerial);
    }

    public function getDeviceName(): ?string
    {
        return $this->getDevices()->firstWhere('serial', $this->deviceSerial)?->name;
    }
}
