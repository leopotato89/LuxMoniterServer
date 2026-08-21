<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Tên')
                    ->searchable(),
                TextColumn::make('username')
                    ->label('Tên đăng nhập')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                IconColumn::make('is_admin')
                    ->label('Admin')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray'),
                IconColumn::make('is_active')
                    ->label('Hoạt động')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),
                TextColumn::make('created_at')
                    ->label('Tạo lúc')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make()->label('Sửa'),
                // Đình chỉ / mở khóa — không áp dụng cho admin khác hoặc chính mình
                Action::make('toggleSuspend')
                    ->label(fn (User $record): string => $record->is_active ? 'Đình chỉ' : 'Mở khóa')
                    ->icon(fn (User $record): string => $record->is_active ? 'heroicon-m-lock-closed' : 'heroicon-m-lock-open')
                    ->color(fn (User $record): string => $record->is_active ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record): string => $record->is_active ? 'Đình chỉ tài khoản' : 'Mở khóa tài khoản')
                    ->modalDescription(fn (User $record): string => $record->is_active
                        ? "Tài khoản {$record->name} sẽ không thể đăng nhập."
                        : "Tài khoản {$record->name} sẽ được phép đăng nhập lại.")
                    ->visible(fn (User $record): bool => $record->id !== auth()->id() && ! $record->isAdmin())
                    ->action(fn (User $record) => $record->update([
                        'is_active' => ! $record->is_active,
                    ])),
                DeleteAction::make()
                    ->label('Xóa')
                    ->visible(fn (User $record): bool => $record->id !== auth()->id() && ! $record->isAdmin()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()->isAdmin()),
                ]),
            ]);
    }
}
