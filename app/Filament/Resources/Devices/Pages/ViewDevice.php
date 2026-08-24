<?php

namespace App\Filament\Resources\Devices\Pages;

use App\Filament\Resources\Devices\DeviceResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDevice extends ViewRecord
{
    protected static string $resource = DeviceResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // ViewRecord chỉ gọi fillForm() khi KHÔNG có infolist, nên default()
        // của DatePicker trong infolist không được hydrate. Tự fill state tại đây.
        $this->getSchema('infolist')?->fill([
            'date' => now()->format('Y-m-d'),
            'period' => 'month',
            'year' => (int) now()->format('Y'),
            'month' => now()->format('Y-m-d'),
        ]);
    }

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
