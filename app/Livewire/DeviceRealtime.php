<?php

namespace App\Livewire;

use App\Services\RealtimeService;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Dữ liệu thời gian thực của một thiết bị — Livewire component ĐỘC LẬP
 * (poll 3s từ Redis) để việc cập nhật chỉ re-render chính nó, không ảnh hưởng
 * các entry khác trên trang (tương tự widget RealtimeOverview ngoài dashboard).
 */
class DeviceRealtime extends Component
{
    public ?string $serial = null;

    public function mount(?string $serial = null): void
    {
        $this->serial = $serial;
    }

    public function render(): View
    {
        $latest = $this->latest();

        return view('livewire.device-realtime', [
            'latest' => $latest,
            'online' => $this->online(),
            'realtimeUrl' => $this->serial ? '/admin/devices/'.$this->serial.'/realtime' : null,
            'timestamp' => $latest ? now()->format('H:i:s d/m/Y') : null,
        ]);
    }

    /**
     * Telemetry mới nhất từ Redis.
     *
     * @return array<string, mixed>|null
     */
    protected function latest(): ?array
    {
        if (! $this->serial) {
            return null;
        }

        return app(RealtimeService::class)->latest($this->serial);
    }

    /**
     * Thiết bị có online không.
     */
    protected function online(): bool
    {
        if (! $this->serial) {
            return false;
        }

        return app(RealtimeService::class)->online($this->serial);
    }
}
