<?php

namespace App\Filament\Resources\Devices\Pages;

use App\Filament\Resources\Devices\DeviceResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDevice extends ViewRecord
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deviceSettings')
                ->label('Cài đặt biến tần')
                ->icon('heroicon-m-cog-6-tooth')
                ->url(fn () => DeviceSettings::getUrl(['record' => $this->record])),
            EditAction::make(),
        ];
    }
}
