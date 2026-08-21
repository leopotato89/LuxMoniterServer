<?php

namespace App\Filament\Resources\Devices\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DeviceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('serial')->label('Serial'),
                TextEntry::make('name')->label('Tên thiết bị')->placeholder('—'),
                TextEntry::make('owner.name')->label('Chủ sở hữu')->placeholder('Chưa gắn'),
                TextEntry::make('device_code')->label('Mã thiết bị')->placeholder('Chưa cấp'),
                IconEntry::make('verified_at')
                    ->label('Đã xác minh')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
                IconEntry::make('enabled')
                    ->label('Đang bật')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->label('Tạo lúc')
                    ->dateTime('d/m/Y H:i'),
            ]);
    }
}
