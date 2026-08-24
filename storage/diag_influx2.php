<?php

use Illuminate\Support\Facades\Http;

$base = config('influx.url').'/api/v2/query?org='.urlencode(config('influx.org'));
$headers = [
    'Authorization' => 'Token '.config('influx.token'),
    'Accept' => 'application/csv',
];
$serial = '4313800597';
$bucket = config('influx.bucket');

// Lấy load_power + grid_voltage + inverter_power cùng lúc trong 24h (pivot theo _time)
$flux = 'from(bucket: "'.$bucket.'")
  |> range(start: -24h)
  |> filter(fn: (r) => r._measurement == "inverter")
  |> filter(fn: (r) => r.device == "'.$serial.'")
  |> filter(fn: (r) => r._field == "load_power" or r._field == "grid_voltage" or r._field == "inverter_power")
  |> aggregateWindow(every: 1m, fn: last, createEmpty: false)
  |> pivot(rowKey: ["_time"], columnKey: ["_field"], valueColumn: "_value")
  |> keep(columns: ["_time", "load_power", "grid_voltage", "inverter_power"])
  |> sort(columns: ["_time"])';

$r = Http::withHeaders($headers)->asJson()->post($base, ['query' => $flux, 'type' => 'flux']);
if (! $r->successful()) {
    echo 'ERROR '.$r->status().' '.$r->body()."\n";
    exit;
}

$header = null;
$rows = [];
foreach (preg_split('/\r?\n/', trim($r->body())) ?: [] as $line) {
    if ($line === '' || str_starts_with($line, '#')) continue;
    $cols = str_getcsv($line);
    if ($header === null) { $header = array_flip($cols); continue; }
    $rows[] = [
        't' => $cols[$header['_time']] ?? '',
        'load' => isset($cols[$header['load_power']]) ? (float) $cols[$header['load_power']] : null,
        'gv' => isset($cols[$header['grid_voltage']]) ? (float) $cols[$header['grid_voltage']] : null,
        'inv' => isset($cols[$header['inverter_power']]) ? (float) $cols[$header['inverter_power']] : null,
    ];
}

$loadZero = array_filter($rows, fn ($x) => $x['load'] !== null && $x['load'] == 0);
$loadZero = array_values($loadZero);

echo "Tong dong (1 phut): ".count($rows)."\n";
echo "Dong load=0: ".count($loadZero)."\n\n";

// Phan tich: trong cac dong load=0, grid_voltage co = 0 khong?
$zero_gv0 = count(array_filter($loadZero, fn ($x) => $x['gv'] === null || $x['gv'] == 0));
$zero_gvNormal = count(array_filter($loadZero, fn ($x) => $x['gv'] !== null && $x['gv'] > 0));
echo "Trong cac dong load=0:\n";
echo "  - grid_voltage=0/null: $zero_gv0\n";
echo "  - grid_voltage>0 (lưới bình thường): $zero_gvNormal\n\n";

// Hien 10 dong load=0 gan day
echo "Mau 10 dong load=0 (gan nhat):\n";
$recent = array_slice(array_reverse($loadZero), 0, 10);
foreach ($recent as $x) {
    printf("  %s  load=%s  gridV=%s  inv=%s\n", $x['t'], var_export($x['load'], true), var_export($x['gv'], true), var_export($x['inv'], true));
}
