<?php

namespace App\Livewire;

use App\Services\InfluxService;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Biểu đồ cột sản lượng — là một Livewire component ĐỘC LẬP để việc đổi
 * period/năm/tháng chỉ re-render chính nó, không ảnh hưởng biểu đồ đường
 * (ChartHistoryEntry) nằm phía trên trong cùng trang.
 */
class EnergyBarChart extends Component
{
    public ?string $serial = null;

    public string $period = 'month';

    public ?int $year = null;

    public ?string $month = null;

    public function mount(?string $serial = null): void
    {
        $this->serial = $serial;
        $this->year = (int) now()->format('Y');
        $this->month = now()->format('Y-m');
    }

    public function render(): View
    {
        return view('livewire.energy-bar-chart', [
            'chart' => $this->buildChart(),
            'yearOptions' => $this->yearOptions(),
            'monthOptions' => $this->monthOptions(),
        ]);
    }

    /**
     * @return array<int, int>
     */
    protected function yearOptions(): array
    {
        $now = now();

        return collect(range($now->year, $now->year - 10))
            ->mapWithKeys(fn (int $y): array => [$y => $y])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function monthOptions(): array
    {
        $now = now();

        return collect(range(0, 23))
            ->mapWithKeys(function (int $offset) use ($now): array {
                $m = (clone $now)->subMonths($offset);

                return [$m->format('Y-m') => 'Tháng '.$m->format('n/Y')];
            })
            ->all();
    }

    /**
     * @return array{labels: array<int, string|int>, datasets: array<int, array<string, mixed>>, options: array<string, mixed>, isEmpty: bool}
     */
    protected function buildChart(): array
    {
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
        $dailyValue = fn (array $row, string $key): float => $key === 'pv'
            ? (float) ($row['pv'] ?? 0) + (float) ($row['accouple'] ?? 0)
            : (float) ($row[$key] ?? 0);

        $labels = [];
        $values = array_fill_keys($seriesKeys, []);

        if (! blank($this->serial)) {
            $tz = config('app.timezone');
            $influx = app(InfluxService::class);
            $period = $this->period;
            $year = (int) ($this->year ?? now()->format('Y'));
            $monthValue = $this->month ? Carbon::createFromFormat('Y-m', $this->month) : now();

            if ($period === 'year') {
                $start = Carbon::createFromDate($year, 1, 1, $tz)->startOfDay();
                $stop = (clone $start)->addYear();

                $rows = $influx->dailyEnergy(
                    $this->serial,
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
                    $this->serial,
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
                    $this->serial,
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
        }

        $datasets = collect($seriesKeys)->map(fn (string $k): array => [
            'label' => $seriesMeta[$k]['label'],
            'data' => $values[$k],
            'backgroundColor' => $seriesMeta[$k]['color'],
            'borderColor' => $seriesMeta[$k]['color'],
            'borderWidth' => 1,
        ])->all();

        $options = [
            'maintainAspectRatio' => false,
            'scales' => [
                'x' => [
                    'grid' => ['color' => 'rgba(0, 0, 0, 0.05)'],
                    'ticks' => ['autoSkip' => true, 'maxTicksLimit' => 15],
                ],
                'y' => [
                    'position' => 'left',
                    'beginAtZero' => true,
                    'grid' => ['color' => 'rgba(0, 0, 0, 0.05)'],
                    'title' => ['display' => true, 'text' => 'Điện năng (kWh)'],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => ['boxWidth' => 12],
                ],
                'tooltip' => ['mode' => 'index', 'intersect' => false],
            ],
            'datasets' => [
                'bar' => [
                    'barPercentage' => 1.0,
                    'categoryPercentage' => 0.95,
                    'maxBarThickness' => 90,
                ],
            ],
        ];

        return [
            'labels' => $labels,
            'datasets' => $datasets,
            'options' => $options,
            'isEmpty' => blank($labels),
        ];
    }
}
