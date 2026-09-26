<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Telemetry realtime của một thiết bị, phát trên kênh riêng `device.{serial}`.
 *
 * `ShouldBroadcastNow` chứ KHÔNG phải `ShouldBroadcast`: dữ liệu telemetry cũ
 * vài giây là vô nghĩa, đẩy qua queue chỉ làm trễ và ứ hàng đợi.
 */
class DeviceTelemetryUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array{online: bool, latest: array<string, mixed>|null, timestamp: string|null}  $snapshot
     */
    public function __construct(
        public readonly string $serial,
        public readonly array $snapshot,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("device.{$this->serial}")];
    }

    /**
     * Tên sự kiện phía client lắng nghe.
     */
    public function broadcastAs(): string
    {
        return 'telemetry';
    }

    /**
     * Payload giống hệt `GET /api/v1/devices/{serial}/realtime` để client dùng lại
     * đúng một hàm xử lý cho cả hai đường (snapshot đầu tiên qua HTTP, sau đó qua WebSocket).
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'serial' => $this->serial,
            'snapshot' => $this->snapshot,
        ];
    }
}
