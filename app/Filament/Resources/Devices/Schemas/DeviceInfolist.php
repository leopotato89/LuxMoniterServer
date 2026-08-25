<?php

namespace App\Filament\Resources\Devices\Schemas;

use App\Filament\Infolists\Components\ChartHistoryEntry;
use App\Livewire\DeviceRealtime;
use App\Livewire\EnergyBarChart;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DeviceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([


                Group::make()
                    ->columnSpan(24)
                    ->schema([
                        Livewire::make(DeviceRealtime::class, fn ($record): array => ['serial' => $record?->serial]),

                    ]),
                // Section::make('Thông tin thiết bị')
                //     ->schema([
                //         TextEntry::make('serial')->label('Serial')->inlineLabel(),
                //         TextEntry::make('name')->label('Tên thiết bị')->placeholder('—')->inlineLabel(),
                //         TextEntry::make('owner.name')->label('Chủ sở hữu')->placeholder('Chưa gắn')->inlineLabel(),
                //         TextEntry::make('device_code')->label('Mã thiết bị')->placeholder('Chưa cấp')->inlineLabel(),
                //         IconEntry::make('verified_at')
                //             ->inlineLabel()
                //             ->label('Đã xác minh')
                //             ->boolean()
                //             ->trueIcon('heroicon-o-check-circle')
                //             ->falseIcon('heroicon-o-x-circle'),
                //         IconEntry::make('enabled')
                //             ->inlineLabel()
                //             ->label('Đang bật')
                //             ->boolean(),
                //         TextEntry::make('created_at')
                //             ->inlineLabel()
                //             ->label('Tạo lúc')
                //             ->dateTime('d/m/Y H:i'),
                //     ])->columns(1)->columnSpan(6),

                Section::make('Lịch sử năng lượng')
                    ->columnSpan(24)
                    ->schema([
                        ChartHistoryEntry::make(),
                    ]),

                Group::make()->columnSpan(24)
                    ->schema([

                        Section::make('Tổng quan')
                            ->schema([
                                Livewire::make(EnergyBarChart::class, fn ($record): array => ['serial' => $record?->serial]),
                            ]),

                    ]),

            ])->columns(24);

    }
}
