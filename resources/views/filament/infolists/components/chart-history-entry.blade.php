@php
    use App\Services\InfluxService;
    use Filament\Support\Facades\FilamentAsset;
    use Filament\Support\View\ComponentAttributeBag as FilamentComponentAttributeBag;
    use Illuminate\Support\Carbon;

    /** @var \App\Models\Device|null $record */
    $serial = $record?->serial;
    $date = $get('date') ?? now()->format('Y-m-d');

    $type = 'line';
    $options = null;
    $cachedData = ['datasets' => [], 'labels' => []];


    if ($serial) {
        $start = Carbon::parse($date, config('app.timezone'))->startOfDay();
        $stop = (clone $start)->addDay();

        $data = app(InfluxService::class)->dashboard(
            $serial,
            $start->copy()->setTimezone('UTC')->toIso8601ZuluString(),
            $stop->copy()->setTimezone('UTC')->toIso8601ZuluString(),
            '1m',
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

        $cachedData = [
            'datasets' => collect($definitions)->map(function (array $def, string $key) use ($data): array {
                $series = $data['series'][$key] ?? [];
                $count = count($series);
                $dataPoints = array_merge([null], $series, [null]);

                // Tìm index đỉnh (|giá trị| lớn nhất) để đánh dấu point
                $pointRadius = array_fill(0, $count + 2, 0);

                if ($count > 0) {
                    $peakAbs = -INF;
                    $peakIdx = 0;

                    foreach ($series as $i => $value) {
                        if (abs($value) > $peakAbs) {
                            $peakAbs = abs($value);
                            $peakIdx = $i;
                        }
                    }

                    // +1 vì data có thêm null ở đầu
                    $pointRadius[$peakIdx + 1] = 5;
                }

                return [
                    'label' => $def['label'],
                    'data' => $dataPoints,
                    'borderColor' => $def['color'],
                    'backgroundColor' => $def['color'].'22',
                    'fill' => false,
                    'tension' => 0.3,
                    'pointRadius' => $pointRadius,
                    'pointHoverRadius' => 5,
                    'pointBackgroundColor' => $def['color'],
                    'pointBorderColor' => '#ffffff',
                    'pointBorderWidth' => 2,
                    'borderWidth' => 2,
                    // SOC dùng trục Y riêng bên phải (0–100); còn lại dùng trục trái (W)
                    'yAxisID' => $key === 'soc' ? 'y1' : 'y',
                    // Đánh dấu series 2 chiều để tooltip hiển thị giá trị dương theo dấu
                    'bidir' => match ($key) {
                        'grid_net' => 'grid',
                        'charge_net' => 'charge',
                        default => null,
                    },
                ];
            })->values()->all(),
            'labels' => $labels,
        ];

        $options = [
            // Tôn trọng chiều cao canvas cố định (tránh chart.js tự resize nhảy kích thước)
            'maintainAspectRatio' => false,
            'scales' => [
                'x' => [
                    'grid' => [
                        'color' => 'rgba(0, 0, 0, 0.05)',
                    ],
                    'ticks' => [
                        'autoSkip' => true,
                        'maxTicksLimit' => 8,
                        'maxRotation' => 0,
                    ],
                ],
                'y' => [
                    'position' => 'left',
                    'grid' => [
                        'color' => 'rgba(0, 0, 0, 0.05)',
                    ],
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
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
        ];
    }

    $isEmpty = blank($cachedData['labels']);
@endphp

@if ($isEmpty)
    <div class="flex items-center justify-center rounded-xl border border-dashed border-gray-300 p-10 text-sm text-gray-500">
        Không có dữ liệu biểu đồ cho thiết bị này.
    </div>
@else
    <style>
        .fi-wi-chart-frame[data-chart-type="line"] canvas {
            height: 400px !important;
            max-height: 400px !important;
        }
    </style>
    <div wire:key="history-chart-{{ $date }}" x-load x-load-src="{{ FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
        data-chart-type="{{ $type }}" x-data="chart({
                    cachedData: @js($cachedData),
                    options: @js($options),
                    type: @js($type),
                })" {{
        (new FilamentComponentAttributeBag)->class([
            'fi-wi-chart-frame',
            'fi-wi-chart-canvas-ctn',
            'fi-wi-chart-frame-no-aspect-ratio',
        ])
    }}>
        <canvas x-ref="canvas" style="width: 100%; height: 400px"></canvas>

        <span x-ref="backgroundColorElement" class="fi-wi-chart-bg-color"></span>
        <span x-ref="borderColorElement" class="fi-wi-chart-border-color"></span>
        <span x-ref="gridColorElement" class="fi-wi-chart-grid-color"></span>
        <span x-ref="textColorElement" class="fi-wi-chart-text-color"></span>
    </div>
@endif
