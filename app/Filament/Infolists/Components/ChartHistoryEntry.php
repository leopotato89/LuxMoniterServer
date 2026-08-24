<?php

namespace App\Filament\Infolists\Components;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class ChartHistoryEntry extends Group
{
    protected string|Closure|null $serial = null;

    /**
     * Mã/serial thiết bị để query dữ liệu biểu đồ. Mặc định lấy từ record đang xem.
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

    /**
     * Tự chứa DatePicker (live + nút trước/sau) và biểu đồ lịch sử.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // KHÔNG dùng wire:poll ở entry này: polling sẽ re-render toàn bộ trang
        // ViewDevice (kể cả component DeviceRealtime chứa diagram SVG), làm phần
        // realtime biến mất. Dữ liệu realtime đã do Alpine fetch lo (3s), còn biểu đồ
        // lịch sử là dữ liệu theo ngày đã chọn nên không cần live-update.

        $this->schema([
            Group::make()->schema([
                DatePicker::make('date')
                    ->label('Ngày')
                    ->hiddenLabel()
                    ->default(now())
                    ->maxDate(now())
                    ->live()
                    ->maxWidth(Width::ExtraSmall)
                    ->suffixAction(
                        Action::make('next')
                            ->label('Sau')
                            ->iconButton()
                            ->icon(Heroicon::ChevronRight)
                            // Không cho đi tới tương lai: disable khi ngày đang chọn >= hôm nay
                            ->disabled(fn (Get $get): bool => Carbon::parse($get('date') ?? now())->startOfDay() >= now()->startOfDay())
                            ->action(function (Get $get, Set $set): void {
                                $next = Carbon::parse($get('date') ?? now())->addDay()->format('Y-m-d');
                                $set('date', $next);
                            })
                    )
                    ->prefixAction(
                        Action::make('prev')
                            ->label('Trước')
                            ->iconButton()
                            ->icon(Heroicon::ChevronLeft)
                            ->action(function (Get $get, Set $set): void {
                                $prev = Carbon::parse($get('date') ?? now())->subDay()->format('Y-m-d');
                                $set('date', $prev);
                            })
                    ),

                // View::make('filament.infolists.components.peak-power'),
                View::make('filament.infolists.components.kpi-power'),
            ]),

            View::make('filament.infolists.components.chart-history-entry'),
        ]);
    }
}
