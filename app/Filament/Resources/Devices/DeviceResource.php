<?php

namespace App\Filament\Resources\Devices;

use App\Filament\Resources\Devices\Pages\CreateDevice;
use App\Filament\Resources\Devices\Pages\EditDevice;
use App\Filament\Resources\Devices\Pages\ListDevices;
use App\Filament\Resources\Devices\Pages\ViewDevice;
use App\Filament\Resources\Devices\Schemas\DeviceForm;
use App\Filament\Resources\Devices\Schemas\DeviceInfolist;
use App\Filament\Resources\Devices\Tables\DevicesTable;
use App\Models\Device;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sun';

    protected static \UnitEnum|string|null $navigationGroup = 'Quản lý';

    protected static ?string $modelLabel = 'Thiết bị';

    protected static ?string $pluralModelLabel = 'Thiết bị';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return DeviceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DeviceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DevicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * Scope theo quyền:
     * - Panel "user" (path /): LUÔN chỉ thấy thiết bị của mình (kể cả admin — bị hạ xuống quyền user).
     * - Panel "admin" (path /admin): admin thấy tất cả; còn lại chỉ thấy thiết bị của mình.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('owner');

        if (Filament::getCurrentPanel()?->getId() === 'user' || ! auth()->user()->isAdmin()) {
            $query->where('owner_id', auth()->id());
        }

        return $query;
    }

    /**
     * Chỉ tạo thiết bị trực tiếp trong panel admin (admin).
     * Ở panel user, user thêm thiết bị qua action "Thêm thiết bị giám sát".
     */
    public static function canCreate(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'admin' && auth()->user()->isAdmin();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDevices::route('/'),
            //            'create' => CreateDevice::route('/create'),
            'view' => ViewDevice::route('/{record}'),
            //            'edit' => EditDevice::route('/{record}/edit'),
        ];
    }
}
