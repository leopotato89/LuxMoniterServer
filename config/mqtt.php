<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MQTT Broker
    |--------------------------------------------------------------------------
    | Cấu hình broker MQTT để gửi lệnh đọc/ghi cài đặt tới thiết bị (ESP32).
    | Local dev dùng broker test cổng 1884; production trỏ broker riêng.
    */

    'host' => env('MQTT_HOST', '127.0.0.1'),

    'port' => (int) env('MQTT_PORT', 1884),

    'username' => env('MQTT_USERNAME'),

    'password' => env('MQTT_PASSWORD'),

    'client_id' => env('MQTT_CLIENT_ID', 'lux-monitor-manager'),

];
