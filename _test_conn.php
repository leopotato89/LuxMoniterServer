<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$influx = app(\App\Services\InfluxService::class);
echo 'InfluxService: '.get_class($influx).PHP_EOL;

// Test Redis
$redis = app('redis')->connection()->client();
echo 'Redis: ';
try {
    $keys = $redis->keys('device:*');
    echo 'OK keys='.json_encode($keys).PHP_EOL;
} catch (\Throwable $e) {
    echo 'FAIL: '.$e->getMessage().PHP_EOL;
}

echo 'Done.'.PHP_EOL;
