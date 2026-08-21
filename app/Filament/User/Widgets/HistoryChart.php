<?php

namespace App\Filament\User\Widgets;

use App\Models\Device;
use App\Services\InfluxService;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class HistoryChart extends ChartWidget
{
    protected ?string $heading = 'Lịch sử';

    protected string $view = 'filament.user.widgets.history-chart';

    protected ?string $pollingInterval = '60s';

    public ?string $deviceSerial = null;

    public ?string $date = null;

    /**
     * Nhận deviceSerial từ Dashboard (select chung cho cả trang).
     */
    protected function getListeners(): array
    {
        return [
            'device-changed' => 'setDeviceSerial',
        ];
    }

    public function setDeviceSerial(string $serial): void
    {
        $this->deviceSerial = $serial;
    }

    public function mount(): void
    {
        $this->deviceSerial ??= $this->getDevices()->first()?->serial;
        $this->date ??= now()->format('Y-m-d');
    }

    public function previousDay(): void
    {
        $this->date = Carbon::parse($this->date)->subDay()->format('Y-m-d');
    }

    public function nextDay(): void
    {
        $this->date = Carbon::parse($this->date)->addDay()->format('Y-m-d');
    }

    /**
     * Bộ lọc ngày (hiển thị bên trong card biểu đồ, cạnh nút trước/sau).
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date')
                    ->label('Ngày')
                    ->live(),
            ]);
    }

    /**
     * Các thiết bị mà user hiện tại có quyền xem (theo panel).
     */
    public function getDevices(): Collection
    {
        $query = Device::query();

        if (Filament::getCurrentPanel()?->getId() === 'user' || ! auth()->user()->isAdmin()) {
            $query->where('owner_id', auth()->id());
        }

        return $query->orderBy('name')->get(['serial', 'name']);
    }

    protected function getData(): array
    {
        if (! $this->deviceSerial) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        // Trục X luôn bắt đầu 00:00 và kết thúc 00:00 hôm sau (của ngày được chọn)
        $this->date ??= now()->format('Y-m-d');
        $start = Carbon::parse($this->date, config('app.timezone'))->startOfDay();
        $stop = (clone $start)->addDay();

        $data = app(InfluxService::class)->dashboard(
            $this->deviceSerial,
            $start->copy()->setTimezone('UTC')->toIso8601ZuluString(),
            $stop->copy()->setTimezone('UTC')->toIso8601ZuluString(),
            '10m',
        );

        $definitions = [
            'soc' => ['label' => 'SOC (%)', 'color' => '#10b981'],
            'pv' => ['label' => 'PV (W)', 'color' => '#f59e0b'],
            'load' => ['label' => 'Tải (W)', 'color' => '#3b82f6'],
            'charge_net' => ['label' => 'Sạc/Xả (W)', 'color' => '#8b5cf6'],
            'grid_net' => ['label' => 'Điện lưới (W)', 'color' => '#ef4444'],
        ];

        // Nhãn trục X theo giờ địa phương (Influx lưu UTC)
        $labels = array_map(
            fn (string $time): string => Carbon::parse($time)
                ->setTimezone(config('app.timezone'))
                ->format('H:i'),
            $data['labels'],
        );

        // Đảm bảo trục X luôn bắt đầu 00:00 và kết thúc 24:00 của ngày được chọn
        array_unshift($labels, '00:00');
        $labels[] = '24:00';

        return [
            'datasets' => collect($definitions)->map(fn (array $def, string $key): array => [
                'label' => $def['label'],
                'data' => array_merge(
                    [null],
                    $data['series'][$key] ?? [],
                    [null],
                ),
                'borderColor' => $def['color'],
                'backgroundColor' => $def['color'].'22',
                'fill' => false,
                'tension' => 0.3,
                'pointRadius' => 0,
                'borderWidth' => 2,
                // SOC dùng trục Y riêng bên phải (0–100); còn lại dùng trục trái (W)
                'yAxisID' => $key === 'soc' ? 'y1' : 'y',
            ])->values()->all(),
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => [
                    'ticks' => [
                        'autoSkip' => true,
                        'maxTicksLimit' => 8,
                        'maxRotation' => 0,
                    ],
                ],
                'y' => [
                    'position' => 'left',
                    'title' => [
                        'display' => true,
                        'text' => 'Công suất (W)',
                    ],
                ],
                // Trục Y riêng cho SOC bên phải: 0 ở dưới, 100 ở trên
                'y1' => [
                    'position' => 'right',
                    'min' => 0,
                    'max' => 100,
                    'title' => [
                        'display' => true,
                        'text' => 'SOC (%)',
                    ],
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => [
                        'boxWidth' => 12,
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
