<?php

namespace App\Filament\Resources\Devices\Pages;

use App\Filament\Resources\Devices\DeviceResource;
use App\Models\Device;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListDevices extends ListRecords
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tạo thiết bị')
                ->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
            // Luồng xác minh: user nhập serial + mã thiết bị (lấy từ ESP32)
            Action::make('addDevice')
                ->label('Thêm thiết bị giám sát')
                ->icon('heroicon-m-plus-circle')
                ->color('success')
                ->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'user')
                ->modalHeading('Thêm thiết bị giám sát')
                ->modalDescription('Nhập serial và mã thiết bị lấy từ trang web của ESP32 để xác minh.')
                ->form([
                    TextInput::make('serial')
                        ->label('Serial thiết bị')
                        ->required()
                        ->maxLength(50),
                    TextInput::make('device_code')
                        ->label('Mã thiết bị (từ ESP32)')
                        ->required()
                        ->maxLength(16),
                ])
                ->action(function (array $data): void {
                    $device = Device::query()->where('serial', trim($data['serial']))->first();
                    $code = trim($data['device_code']);

                    if (! $device || ! hash_equals((string) $device->device_code, $code)) {
                        Notification::make()
                            ->title('Mã thiết bị không hợp lệ')
                            ->body('Kiểm tra lại serial và mã thiết bị lấy từ ESP32.')
                            ->danger()
                            ->send();

                        return;
                    }

                    if ($device->owner_id === auth()->id()) {
                        Notification::make()
                            ->title('Thiết bị đã thuộc tài khoản của bạn')
                            ->warning()
                            ->send();

                        return;
                    }

                    if ($device->isClaimed()) {
                        Notification::make()
                            ->title('Thiết bị đã thuộc tài khoản khác')
                            ->danger()
                            ->send();

                        return;
                    }

                    $device->update([
                        'owner_id' => auth()->id(),
                        'verified_at' => now(),
                    ]);

                    Notification::make()
                        ->title('Đã thêm thiết bị giám sát')
                        ->body('Thiết bị '.$device->serial.' đã được gắn vào tài khoản của bạn.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
