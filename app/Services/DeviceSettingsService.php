<?php

namespace App\Services;

use App\Filament\Resources\Devices\Schemas\DeviceSetup;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\User;
use App\Support\InverterSettings;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

/**
 * Điều phối luồng đọc/ghi cài đặt biến tần qua cloud:
 *   Đọc:  publishRead → ESP32 đọc Modbus → cmd/result → Redis → parse → giá trị form
 *   Lưu:  diff field thay đổi → publish từng lệnh ghi → ghi audit device_commands
 */
class DeviceSettingsService
{
    public function __construct(
        private readonly MqttService $mqtt,
        private readonly RealtimeService $realtime,
    ) {}

    /**
     * Đọc cấu hình hiện tại từ thiết bị.
     *
     * @return array<string, mixed> key => giá trị form
     */
    public function read(Device $device, int $timeoutSec = 25): array
    {
        $this->realtime->clearCommandResult($device->serial);

        if (! $this->mqtt->publishRead($device->serial)) {
            throw new RuntimeException('Không gửi được lệnh đọc MQTT.');
        }

        $regs = $this->pollRegs($device->serial, $timeoutSec);

        return InverterSettings::valuesFromRegs($regs);
    }

    /**
     * Ghi các thay đổi xuống thiết bị + ghi nhật ký audit.
     *
     * @param  array<string, mixed>  $new  key => giá trị form mới
     * @param  array<string, mixed>  $current  key => giá trị form hiện tại (đã đọc)
     * @return array<int, DeviceCommand>
     */
    public function save(Device $device, array $new, array $current, ?User $user = null): array
    {
        $writes = InverterSettings::writesFromChanges($new, $current);

        $audit = [];

        foreach ($writes as $w) {
            $sent = $this->mqtt->publishSettings(
                $device->serial,
                $w['reg'],
                $w['value'],
                $w['bit'] ?? null,
                $w['bits'] ?? null,
            );

            $audit[] = $device->commands()->create([
                'user_id' => $user?->id,
                'reg' => $w['reg'],
                'bit' => $w['bit'] ?? null,
                'value' => $w['value'],
                'request_json' => json_encode($w),
                'status' => $sent ? 'sent' : 'failed',
                'error' => $sent ? null : 'Không gửi được MQTT',
            ]);
        }

        return $audit;
    }

    /**
     * Ghi NGAY 1 field đơn lẻ (theo key "reg_X" hoặc "reg_X_bY") xuống thiết bị + audit.
     */
    public function saveField(Device $device, string $key, mixed $value, ?User $user = null): bool
    {
        $write = InverterSettings::writeForField($key, $value);

        if ($write === null) {
            throw new RuntimeException("Không tìm thấy field '{$key}'.");
        }

        $sent = $this->mqtt->publishSettings(
            $device->serial,
            $write['reg'],
            $write['value'],
            $write['bit'] ?? null,
            $write['bits'] ?? null,
        );

        $device->commands()->create([
            'user_id' => $user?->id,
            'reg' => $write['reg'],
            'bit' => $write['bit'] ?? null,
            'value' => $write['value'],
            'request_json' => json_encode($write),
            'status' => $sent ? 'sent' : 'failed',
            'error' => $sent ? null : 'Không gửi được MQTT',
        ]);

        return $sent;
    }

    /**
     * Action modal "Cài đặt biến tần" — tự đọc cấu hình khi mở, lưu theo từng field.
     */
    public function action(Device $device): Action
    {
        return Action::make('deviceSettings')
        
            ->label('Cài đặt biến tần')
            ->icon('heroicon-m-cog-6-tooth')
            ->modalWidth(Width::ExtraLarge)
            ->schema(DeviceSetup::settingsSchema(
                fn (string $key, mixed $value) => $this->saveFieldAndNotify($device, $key, $value)
            ))
            ->fillForm(fn (): array => $this->readAndNotify($device))
            ->modalSubmitActionLabel('Tải lại dữ liệu')
            // Bấm "Tải lại dữ liệu" → đọc lại cấu hình, giữ modal mở.
            ->action(function (Action $action) use ($device): void {
                $action->fillForm($this->readAndNotify($device));
                $action->halt();
            });
    }

    /**
     * Đọc cấu hình; nếu lỗi thì báo notification và trả mảng rỗng.
     */
    private function readAndNotify(Device $device): array
    {
        try {
            return $this->read($device);
        } catch (Throwable $e) {
            Notification::make()
                ->title('Không đọc được cấu hình')
                ->body($e->getMessage().' Thiết bị có thể đang offline.')
                ->danger()
                ->send();

            return [];
        }
    }

    /**
     * Ghi 1 field; báo notification thành công/lỗi.
     */
    private function saveFieldAndNotify(Device $device, string $key, mixed $value): void
    {
        try {
            $this->saveField($device, $key, $value, auth()->user());
            Notification::make()->title('Đã lưu cài đặt')->success()->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Lỗi lưu cài đặt')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Chờ bản tin {"action":"read","regs":{...}} trên Redis.
     *
     * @return array<int, int> reg => raw value
     */
    private function pollRegs(string $serial, int $timeoutSec): array
    {
        $deadline = Carbon::now()->addSeconds($timeoutSec);

        while (Carbon::now()->lessThan($deadline)) {
            $result = $this->realtime->commandResult($serial);

            if ($result !== null) {
                $data = json_decode($result, true);

                if (
                    is_array($data)
                    && ($data['action'] ?? null) === 'read'
                    && is_array($data['regs'] ?? null)
                ) {
                    return array_map('intval', $data['regs']);
                }
            }

            // Poll nhanh (~100ms) để rút ngắn độ trễ nhận kết quả từ thiết bị.
            usleep(100_000);
        }

        throw new RuntimeException("Thiết bị không phản hồi lệnh đọc trong {$timeoutSec}s.");
    }
}
