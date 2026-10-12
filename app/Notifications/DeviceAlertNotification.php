<?php

namespace App\Notifications;

use App\Enums\AlertType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

/**
 * Cảnh báo thiết bị: MỘT thông báo, ba nơi nhận cùng nội dung.
 *
 *  - database  → bảng `notifications` (lịch sử + đã đọc/chưa đọc)
 *  - broadcast → Reverb, kênh private `App.Models.User.{id}`
 *  - fcm       → push về điện thoại
 */
class DeviceAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly AlertType $type,
        public readonly string $deviceSerial,
        public readonly string $title,
        public readonly string $body,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', FcmChannel::class];
    }

    /**
     * Payload dùng chung cho database và broadcast.
     *
     * @return array{device_serial: string, type: string, severity: string, title: string, body: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'device_serial' => $this->deviceSerial,
            'type' => $this->type->value,
            'severity' => $this->type->severity()->value,
            'title' => $this->title,
            'body' => $this->body,
        ];
    }

    /**
     * Data của FCM phải là chuỗi; app dùng `device_serial` + `notification_id` để deep-link.
     */
    public function toFcm(object $notifiable): FcmMessage
    {
        return new FcmMessage(
            notification: new FcmNotification(title: $this->title, body: $this->body),
            data: [
                'device_serial' => $this->deviceSerial,
                'notification_id' => (string) $this->id,
                'type' => $this->type->value,
            ],
        );
    }
}
