<?php

return [

    /*
    |--------------------------------------------------------------------------
    | InfluxDB 2.x (time-series)
    |--------------------------------------------------------------------------
    | Khớp với cloud/worker/config.js (org/bucket/token dùng chung).
    */

    'url' => env('INFLUX_URL', 'http://localhost:8086'),
    'token' => env('INFLUX_TOKEN', ''),
    'org' => env('INFLUX_ORG', 'luxmonitor'),
    'bucket' => env('INFLUX_BUCKET', 'telemetry'),
];
