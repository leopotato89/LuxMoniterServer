<?php
require __DIR__.'/vendor/autoload.php';

use Predis\Client;

try {
    $client = new Client(['host' => '192.168.1.76', 'port' => 6379]);
    $client->connect();
    echo 'PING: '.$client->ping().PHP_EOL;
    $keys = $client->keys('device:*');
    echo 'KEYS: '.json_encode($keys).PHP_EOL;
    if ($keys) {
        echo 'ONLINE: '.$client->get($keys[0]).PHP_EOL;
    }
} catch (\Throwable $e) {
    echo 'FAIL: '.get_class($e).': '.$e->getMessage().PHP_EOL;
}
