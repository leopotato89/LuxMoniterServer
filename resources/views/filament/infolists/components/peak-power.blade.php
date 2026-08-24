@php
    use App\Services\InfluxService;
    use Illuminate\Support\Carbon;

    /** @var \App\Models\Device|null $record */
    $serial = $record?->serial;
    $date = $get('date') ?? now()->format('Y-m-d');

    $metrics = [];

    if ($serial) {
        $start = Carbon::parse($date, config('app.timezone'))->startOfDay();
        $stop = (clone $start)->addDay();

        $data = app(InfluxService::class)->dashboard(
            $serial,
            $start->copy()->setTimezone('UTC')->toIso8601ZuluString(),
            $stop->copy()->setTimezone('UTC')->toIso8601ZuluString(),
            '10m',
        );

        $definitions = [
            'pv' => ['label' => 'PV đỉnh', 'unit' => 'W'],
            'load' => ['label' => 'Tải đỉnh', 'unit' => 'W'],
            'export_power' => ['label' => 'Đẩy lưới đỉnh', 'unit' => 'W'],
            'import_power' => ['label' => 'Lấy lưới đỉnh', 'unit' => 'W'],
        ];

        foreach ($definitions as $key => $def) {
            $series = $data['series'][$key] ?? [];

            if ($series === []) {
                $metrics[] = ['label' => $def['label'], 'value' => '—', 'peakTime' => null, 'unit' => $def['unit']];

                continue;
            }

            // Tìm index có |giá trị| lớn nhất (đỉnh, bất kể hướng sạc/xả, nhập/xuất)
            $peakIndex = null;
            $peakAbs = -INF;

            foreach ($series as $index => $value) {
                $mag = in_array($key, ['export_power', 'import_power'], true) ? (float) $value : abs($value);

                if ($mag > $peakAbs) {
                    $peakAbs = $mag;
                    $peakIndex = $index;
                }
            }

            $peakValue = $series[$peakIndex] ?? null;
            $peakLabel = $data['labels'][$peakIndex] ?? null;

            // Đẩy/Lấy lưới luôn hiển thị số dương
            $displayValue = in_array($key, ['export_power', 'import_power'], true)
                ? abs($peakValue)
                : $peakValue;

            $metrics[] = [
                'label' => $def['label'],
                'value' => number_format($displayValue, 0),
                'unit' => $def['unit'],
                'peakTime' => $peakLabel
                    ? Carbon::parse($peakLabel)->setTimezone(config('app.timezone'))->format('H:i')
                    : null,
            ];
        }
    }
@endphp

<div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
    @foreach ($metrics as $metric)
        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $metric['label'] }} - {{ $metric['peakTime'] }}</div>
            <div class="mt-1 text-2xl font-semibold">
                {{ $metric['value'] }}
                <span class="text-sm font-normal text-gray-400">W</span>
            </div>
        </div>
    @endforeach
</div>
