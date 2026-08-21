<?php

namespace App\Filament\Resources\Devices\Pages;

use App\Filament\Resources\Devices\DeviceResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditDevice extends EditRecord
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deviceSettings')
                ->label('Cài đặt biến tần')
                ->icon('heroicon-m-cog-6-tooth')
                ->url(fn () => DeviceSettings::getUrl(['record' => $this->record])),
            ViewAction::make(),
            DeleteAction::make()
                ->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
        ];
    }
}
