<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

/**
 * Gửi lệnh MQTT tới thiết bị (ESP32).
 *
 * ESP32 subscribe topic `luxmonitor/<serial>/cmd/settings`:
 *   - {"action":"read"}                        → đọc register, trả về cmd/result
 *   - {"reg":X,"value":Y}                      → ghi register
 *   - {"reg":X,"bit":B,"value":V}              → ghi 1 bit (read-modify-write)
 * ESP32 trả kết quả trên `luxmonitor/<serial>/cmd/result`
 * (worker relay → Redis device:<serial>:cmd_result).
 */
class MqttService
{
    /**
     * Yêu cầu thiết bị đọc cấu hình hiện tại (trả về trên cmd/result).
     */
    public function publishRead(string $serial): bool
    {
        return $this->publish("luxmonitor/{$serial}/cmd/settings", '{"action":"read"}');
    }

    /**
     * Ghi giá trị lên thiết bị (register, hoặc 1/1 bitfield nếu có $bit).
     */
    public function publishSettings(string $serial, int $reg, int $value, ?int $bit = null, ?int $bits = null): bool
    {
        $payload = ['reg' => $reg, 'value' => $value];

        if ($bit !== null) {
            $payload['bit'] = $bit;
            $payload['bits'] = $bits ?? 1;
        }

        return $this->publish("luxmonitor/{$serial}/cmd/settings", (string) json_encode($payload));
    }

    /**
     * Publish 1 message (kết nối ngắn, gửi xong ngắt).
     */
    public function publish(string $topic, string $payload, int $qos = 0, bool $retain = false): bool
    {
        $clientId = config('mqtt.client_id').'-'.bin2hex(random_bytes(4));

        try {
            $client = new MqttClient(
                config('mqtt.host'),
                config('mqtt.port'),
                $clientId,
            );

            $settings = (new ConnectionSettings)
                ->setConnectTimeout(5)
                ->setKeepAliveInterval(30);

            if (config('mqtt.username')) {
                $settings->setUsername(config('mqtt.username'));
            }
            if (config('mqtt.password')) {
                $settings->setPassword(config('mqtt.password'));
            }

            $client->connect($settings, true);
            $client->publish($topic, $payload, $qos, $retain);
            $client->disconnect();

            return true;
        } catch (\Throwable $e) {
            Log::error('[MqttService] publish thất bại: '.$e->getMessage());

            return false;
        }
    }
}
