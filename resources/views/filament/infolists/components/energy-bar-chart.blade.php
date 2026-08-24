@php
    use App\Services\InfluxService;
    use Filament\Support\Facades\FilamentAsset;
    use Filament\Support\View\ComponentAttributeBag as FilamentComponentAttributeBag;
    use Illuminate\Support\Carbon;

    /** @var \App\Models\Device|null $record */
    $serial = $record?->serial;
    $period = $get('period') ?? 'year';
    $year = (int) ($get('year') ?? now()->format('Y'));
    $monthValue = $get('month') ? Carbon::parse($get('month')) : now();

    $seriesKeys = ['pv', 'charge', 'discharge', 'load', 'import', 'export'];

    $seriesMeta = [
        'pv' => ['label' => 'Sản lượng PV (kWh)', 'color' => '#f59e0b'],
        'charge' => ['label' => 'Sạc pin (kWh)', 'color' => '#8b5cf6'],
        'discharge' => ['label' => 'Xả pin (kWh)', 'color' => '#6366f1'],
        'load' => ['label' => 'Tải sử dụng (kWh)', 'color' => '#3b82f6'],
        'import' => ['label' => 'Lấy lưới (kWh)', 'color' => '#ef4444'],
        'export' => ['label' => 'Đẩy lưới (kWh)', 'color' => '#10b981'],
    ];

    // Giá trị một series từ row trong ngày (PV = pv + AC Couple)
    $dailyValue = function (array $row, string $key): float {
        if ($key === 'pv') {
            return ($row['pv'] ?? 0) + ($row['accouple'] ?? 0);
        }

        return $row[$key] ?? 0;
    };

    $type = 'bar';
    $labels = [];
    $values = array_fill_keys($seriesKeys, []);
    $options = null;

    if ($serial) {
        $tz = config('app.timezone');
        $influx = app(InfluxService::class);

        if ($period === 'year') {
            $start = Carbon::createFromDate($year, 1, 1, $tz)->startOfDay();
            $stop = (clone $start)->addYear();

            $rows = $influx->dailyEnergy(
                $serial,
                $start->copy()->setTimezone('UTC')->toIso8601ZuluString(),
                $stop->copy()->setTimezone('UTC')->toIso8601ZuluString(),
            );

            foreach (range(1, 12) as $m) {
                $labels[] = 'T'.$m;
                $sums = array_fill_keys($seriesKeys, 0.0);

                foreach ($rows as $date => $row) {
                    if ((int) substr($date, 0, 4) === $year && (int) substr($date, 5, 2) === $m) {
                        foreach ($seriesKeys as $k) {
                            $sums[$k] += $dailyValue($row, $k);
                        }
                    }
                }

                foreach ($seriesKeys as $k) {
                    $values[$k][] = round($sums[$k], 2);
                }
            }
        } elseif ($period === 'month') {
            $selYear = (int) $monthValue->format('Y');
            $selMonth = (int) $monthValue->format('m');
            $daysInMonth = Carbon::createFromDate($selYear, $selMonth, 1, $tz)->daysInMonth;
            $start = Carbon::createFromDate($selYear, $selMonth, 1, $tz)->startOfDay();
            $stop = (clone $start)->addMonth();

            $rows = $influx->dailyEnergy(
                $serial,
                $start->copy()->setTimezone('UTC')->toIso8601ZuluString(),
                $stop->copy()->setTimezone('UTC')->toIso8601ZuluString(),
            );

            foreach (range(1, $daysInMonth) as $d) {
                $labels[] = $d;
                $row = $rows[sprintf('%04d-%02d-%02d', $selYear, $selMonth, $d)] ?? null;

                foreach ($seriesKeys as $k) {
                    $values[$k][] = $row ? round($dailyValue($row, $k), 2) : 0.0;
                }
            }
        } else { // Tất cả các năm
            $start = Carbon::createFromDate(2000, 1, 1, $tz)->startOfDay();
            $stop = now();

            $rows = $influx->dailyEnergy(
                $serial,
                $start->copy()->setTimezone('UTC')->toIso8601ZuluString(),
                $stop->copy()->setTimezone('UTC')->toIso8601ZuluString(),
            );

            $byYear = [];

            foreach ($rows as $date => $row) {
                $y = (int) substr($date, 0, 4);

                if (! isset($byYear[$y])) {
                    $byYear[$y] = array_fill_keys($seriesKeys, 0.0);
                }

                foreach ($seriesKeys as $k) {
                    $byYear[$y][$k] += $dailyValue($row, $k);
                }
            }

            ksort($byYear);

            foreach ($byYear as $y => $sums) {
                $labels[] = (string) $y;

                foreach ($seriesKeys as $k) {
                    $values[$k][] = round($sums[$k], 2);
                }
            }
        }

        $datasets = collect($seriesKeys)->map(fn (string $k): array => [
            'label' => $seriesMeta[$k]['label'],
            'data' => $values[$k],
            'backgroundColor' => $seriesMeta[$k]['color'],
            'borderColor' => $seriesMeta[$k]['color'],
            'borderWidth' => 1,
        ])->all();

        $cachedData = ['datasets' => $datasets, 'labels' => $labels];

        $options = [
            'maintainAspectRatio' => false,
            'scales' => [
                'x' => [
                    'grid' => [
                        'color' => 'rgba(0, 0, 0, 0.05)',
                    ],
                    'ticks' => [
                        'autoSkip' => true,
                        'maxTicksLimit' => 15,
                    ],
                ],
                'y' => [
                    'position' => 'left',
                    'beginAtZero' => true,
                    'grid' => [
                        'color' => 'rgba(0, 0, 0, 0.05)',
                    ],
                    'title' => [
                        'display' => true,
                        'text' => 'Điện năng (kWh)',
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

    $isEmpty = blank($cachedData['labels'] ?? []);
@endphp

@if ($isEmpty)
    <div class="flex items-center justify-center rounded-xl border border-dashed border-gray-300 p-10 text-sm text-gray-500">
        Không có dữ liệu sản lượng cho thiết bị này.
    </div>
@else
    <div wire:key="energy-bar-{{ $period }}-{{ $year }}-{{ $monthValue->format('Y-m') }}" x-load x-load-src="{{ FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
        data-chart-type="bar" x-data="chart({
                    cachedData: @js($cachedData),
                    options: @js($options),
                    type: 'bar',
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
