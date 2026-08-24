<?php

use Illuminate\Support\Facades\Http;

$base = config('influx.url').'/api/v2/query?org='.urlencode(config('influx.org'));
$headers = [
    'Authorization' => 'Token '.config('influx.token'),
    'Accept' => 'application/csv',
];

$serial = '4313800597';
$bucket = config('influx.bucket');

// 1) Các field chứa "load" trong measurement inverter (tìm field theo pha)
$fluxFields = 'import "influxdata/influxdb/schema"
schema.measurementFieldKeys(bucket: "'.$bucket.'", measurement: "inverter", start: -7d)
  |> filter(fn: (r) => r._field =~ /load/)';

echo "=== FIELDS chua 'load' ===\n";
$r = Http::withHeaders($headers)->asJson()->post($base, ['query' => $fluxFields, 'type' => 'flux']);
echo ($r->successful() ? $r->body() : 'ERROR '.$r->status().' '.$r->body())."\n\n";

// 2) load_power 24h qua: dem so lan = 0, gia tri min/max, so diem
$flux = 'from(bucket: "'.$bucket.'")
  |> range(start: -24h)
  |> filter(fn: (r) => r._measurement == "inverter")
  |> filter(fn: (r) => r._field == "load_power")
  |> filter(fn: (r) => r.device == "'.$serial.'")
  |> aggregateWindow(every: 1m, fn: last, createEmpty: false)
  |> keep(columns: ["_time", "_value"])';

echo "=== load_power 24h (chi tiet: dem 0, min, max, count) ===\n";
$r2 = Http::withHeaders($headers)->asJson()->post($base, ['query' => $flux, 'type' => 'flux']);
if (! $r2->successful()) {
    echo 'ERROR '.$r2->status().' '.$r2->body()."\n";
    exit;
}
$header = null;
$vals = [];
foreach (preg_split('/\r?\n/', trim($r2->body())) ?: [] as $line) {
    if ($line === '' || str_starts_with($line, '#')) continue;
    $cols = str_getcsv($line);
    if ($header === null) { $header = array_flip($cols); continue; }
    $v = (float) ($cols[$header['_value']] ?? 0);
    $vals[] = $v;
}
$count = count($vals);
$zeros = count(array_filter($vals, fn ($v) => $v == 0));
echo "tong diem: $count\n";
echo "so diem = 0: $zeros (".round($count ? $zeros / $count * 100 : 0, 2)."%)\n";
echo "min: ".($count ? min($vals) : '-')."  max: ".($count ? max($vals) : '-')."\n";

// 3) Xem co nhieu field rieng cho 3 pha khong (LI/L2/L3) bang cach liet ke field keys
$fluxAll = 'import "influxdata/influxdb/schema"
schema.measurementFieldKeys(bucket: "'.$bucket.'", measurement: "inverter", start: -7d)';
echo "\n=== ALL fields ===\n";
$r3 = Http::withHeaders($headers)->asJson()->post($base, ['query' => $fluxAll, 'type' => 'flux']);
echo ($r3->successful() ? $r3->body() : 'ERROR '.$r3->status())."\n";
