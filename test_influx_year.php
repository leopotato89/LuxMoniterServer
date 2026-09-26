<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$serial = '4313800597';
$start = '2026-01-01T00:00:00Z';
$stop = '2026-12-31T23:59:59Z';
$fields = ['pv_energy_day', 'load_energy_day'];

$flux = sprintf(
    'from(bucket: "%s")
|> range(start: %s, stop: %s)
|> filter(fn: (r) => r._measurement == "inverter")
|> filter(fn: (r) => r.device == "%s")
|> filter(fn: (r) => %s)
|> aggregateWindow(every: 1d, fn: max, createEmpty: false)
|> aggregateWindow(every: 1mo, fn: sum, createEmpty: false)
|> pivot(rowKey: ["_time"], columnKey: ["_field"], valueColumn: "_value")',
    config('influx.bucket'),
    $start,
    $stop,
    $serial,
    implode(' or ', array_map(fn ($f) => 'r._field == "'.$f.'"', $fields))
);

$response = Http::withHeaders([
    'Authorization' => 'Token '.config('influx.token'),
    'Accept' => 'application/csv',
])->asJson()->post(config('influx.url').'/api/v2/query?org='.urlencode(config('influx.org')), [
    'query' => $flux,
    'type' => 'flux',
]);

echo $response->body();
