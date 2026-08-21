<?php

namespace App\Filament\Resources\Devices\Schemas;

use App\Models\Device;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DeviceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Tên thiết bị')
                    ->maxLength(255),
                TextInput::make('serial')
                    ->label('Serial')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50),
                Select::make('owner_id')
                    ->label('Chủ sở hữu')
                    ->relationship('owner', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->visible(fn (): bool => auth()->user()->isAdmin()),
                TextInput::make('device_code')
                    ->label('Mã thiết bị')
                    ->disabled()
                    ->dehydrated(false)
                    ->visible(fn (?Device $record): bool => $record !== null),
                Toggle::make('enabled')
                    ->label('Đang bật'),
            ]);
    }
}
