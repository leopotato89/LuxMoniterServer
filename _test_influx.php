<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$influx = app(\App\Services\InfluxService::class);
echo 'InfluxService: '.get_class($influx).PHP_EOL;

// Thử gọi 1 phương thức public nếu có (dashboard)
if (method_exists($influx, 'history')) {
    try {
        $data = $influx->history('4313800597', 'battery_soc', '-30m', 'now()', '5m');
        echo 'HISTORY battery_soc: '.gettype($data).' count='.(is_array($data) ? count($data) : '?').PHP_EOL;
        echo substr(json_encode($data), 0, 400).PHP_EOL;
    } catch (\Throwable $e) {
        echo 'HISTORY FAIL: '.$e->getMessage().PHP_EOL;
    }
} else {
    echo 'NO history() method. Public methods: '.implode(', ', get_class_methods($influx)).PHP_EOL;
}
echo 'Done.'.PHP_EOL;
