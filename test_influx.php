<?php

use App\Services\InfluxService;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$influx = app(InfluxService::class);
$start = Carbon::parse('2026-09-01')->setTimezone('UTC')->toIso8601ZuluString();
$stop = Carbon::parse('2026-09-30')->setTimezone('UTC')->toIso8601ZuluString();

$data = $influx->dailyEnergy('4313800597', $start, $stop);
print_r($data);
