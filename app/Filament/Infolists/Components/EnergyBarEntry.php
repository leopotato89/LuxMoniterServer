<?php

namespace App\Filament\Infolists\Components;

use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\Width;

/**
 * Biểu đồ cột sản lượng: 6 thông số (Sản lượng PV, Sạc pin, Xả pin, Tải sử dụng,
 * Lấy lưới, Đẩy lưới) thống kê theo Năm / Tháng / Tất cả các năm.
 */
class EnergyBarEntry extends Group
{
    protected string|Closure|null $serial = null;

    /**
     * Mã/serial thiết bị để query dữ liệu. Mặc định lấy từ record đang xem.
     */
    public function serial(string|Closure|null $serial): static
    {
        $this->serial = $serial;

        return $this;
    }

    public function getSerial(): ?string
    {
        return $this->evaluate($this->serial) ?? $this->getRecord()?->serial;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $now = now();

        $this->schema([
            Group::make()
                ->columns(2)
                ->schema([
                    Radio::make('period')
                        ->label('')
                        ->hiddenLabel()
                        ->inlineLabel()
                        ->options([
                            'month' => 'Tháng',
                            'year' => 'Năm',
                            'all' => 'Tất cả',
                        ])
                        ->default('month')
                        ->inline()
                        ->live(),

                    Select::make('year')
                        ->label('Năm')
                        ->inlineLabel()
                        ->options(
                            collect(range($now->year, $now->year - 10))
                                ->mapWithKeys(fn (int $y): array => [$y => (string) $y])
                                ->all()
                        )
                        ->maxWidth(Width::ExtraSmall)
                        ->default($now->year)
                        ->searchable()
                        ->live()
                        ->visible(fn (Get $get): bool => $get('period') === 'year'),

                    DatePicker::make('month')
                        ->default(now())
                        ->inlineLabel()
                        ->maxWidth(Width::ExtraSmall)
                        ->live()
                        ->displayFormat('m/Y')
                        ->native(false)
                        ->visible(fn (Get $get): bool => $get('period') === 'month'),
                ]),

            View::make('filament.infolists.components.energy-bar-chart'),
        ]);
    }
}
