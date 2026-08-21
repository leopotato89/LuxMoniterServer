<?php

namespace App\Filament\Resources\Devices\Tables;

use App\Models\Device;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class DevicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('serial')
                    ->label('Serial')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Tên thiết bị')
                    ->searchable(),
                TextColumn::make('owner.name')
                    ->label('Chủ sở hữu')
                    ->placeholder('Chưa gắn'),
                IconColumn::make('verified_at')
                    ->label('Đã xác minh')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->trueColor('success')
                    ->falseIcon('heroicon-o-x-circle')
                    ->falseColor('danger'),
                ToggleColumn::make('enabled')
                    ->label('Bật'),
                TextColumn::make('created_at')
                    ->label('Tạo lúc')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('owner_id')
                    ->label('Trạng thái gắn chủ')
                    ->trueLabel('Đã gắn chủ')
                    ->falseLabel('Chưa gắn'),
            ])
            ->recordActions([
                ViewAction::make()->label('Xem'),
                EditAction::make()->label('Sửa'),
                DeleteAction::make()
                    ->label('Xóa')
                    ->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
                ]),
            ]);
    }
}
