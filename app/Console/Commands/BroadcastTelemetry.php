<?php

namespace App\Console\Commands;

use App\Events\DeviceTelemetryUpdated;
use App\Services\RealtimeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Pusher\Pusher;
use Throwable;
use TypeError;

/**
 * Đọc Redis và phát telemetry lên kênh WebSocket của các thiết bị ĐANG CÓ NGƯỜI XEM.
 *
 * Đây là mấu chốt về tải: chi phí là 1 vòng lặp cho toàn hệ thống, không phải N vòng
 * lặp cho N người dùng. Số người xem tăng không làm tăng số lần đọc Redis.
 *
 * Chỉ phát khi số liệu ĐỔI (so dấu vân tay của chuỗi thô trong Redis) — vừa tránh
 * broadcast vô ích, vừa tránh gọi RealtimeService::snapshot() mỗi giây, vì hàm đó có
 * thể chạm InfluxDB khi worker không ghi field `time`.
 */
class BroadcastTelemetry extends Command
{
    protected $signature = 'telemetry:broadcast
        {--once : Quét một lượt rồi thoát}
        {--dry-run : Chỉ in ra, không phát}
        {--interval=1 : Số giây giữa hai lượt quét}';

    protected $description = 'Phát telemetry realtime lên kênh WebSocket của các thiết bị đang có người xem';

    /** Bao lâu làm mới danh sách thiết bị đang có người xem (giây). */
    private const WATCH_REFRESH = 5;

    /** Tiền tố kênh riêng: PrivateChannel('device.X') → private-device.X */
    private const CHANNEL_PREFIX = 'private-device.';

    public function handle(RealtimeService $realtime): int
    {
        $once = (bool) $this->option('once');
        $dryRun = (bool) $this->option('dry-run');
        $interval = max(1, (int) $this->option('interval'));

        /** @var array<string, true> $watched */
        $watched = [];

        /** @var array<string, string> $fingerprints */
        $fingerprints = [];

        $nextRefresh = 0.0;

        while (true) {
            if (microtime(true) >= $nextRefresh) {
                $watched = $this->watchedSerials();
                $fingerprints = array_intersect_key($fingerprints, $watched);
                $nextRefresh = microtime(true) + self::WATCH_REFRESH;

                $this->line(sprintf('%s — %d thiết bị đang có người xem', now()->format('H:i:s'), count($watched)));
            }

            foreach (array_keys($watched) as $serial) {
                $fingerprint = $this->fingerprint($serial);

                if (($fingerprints[$serial] ?? null) === $fingerprint) {
                    continue;
                }

                $fingerprints[$serial] = $fingerprint;
                $snapshot = $realtime->snapshot($serial);

                if ($dryRun) {
                    $this->line(sprintf(
                        '  [dry-run] %s → online=%s, %d field',
                        $serial,
                        $snapshot['online'] ? '1' : '0',
                        count($snapshot['latest'] ?? []),
                    ));

                    continue;
                }

                DeviceTelemetryUpdated::dispatch($serial, $snapshot);
            }

            if ($once) {
                return self::SUCCESS;
            }

            sleep($interval);
        }
    }

    /**
     * Dấu vân tay của trạng thái thô trong Redis — đổi thì mới có việc để làm.
     */
    private function fingerprint(string $serial): string
    {
        return md5(
            (string) Redis::get("device:{$serial}:latest")
            .'|'.(string) Redis::get("device:{$serial}:online"),
        );
    }

    /**
     * Serial của các thiết bị đang có ít nhất một người nghe.
     *
     * Hỏi thẳng Reverb qua API kênh (giao thức Pusher) nên chỉ đọc Redis cho thiết bị
     * đang được xem, không quét toàn bộ bảng devices.
     *
     * @return array<string, true>
     */
    private function watchedSerials(): array
    {
        try {
            $response = $this->pusher()->getChannels([
                'filter_by_prefix' => self::CHANNEL_PREFIX,
                'info' => 'subscription_count',
            ]);

            $channels = get_object_vars($response)['channels'] ?? [];
        } catch (TypeError) {
            // Reverb trả `"channels": []` (mảng rỗng) khi chưa có kênh nào, còn SDK Pusher
            // lại gọi get_object_vars() lên giá trị đó nên ném TypeError. Không kênh = không ai nghe.
            $channels = [];
        } catch (Throwable $e) {
            // Reverb chưa chạy: không có ai đang nghe thì cũng không có gì để phát.
            $this->warn('Không lấy được danh sách kênh: '.$e->getMessage());

            $channels = [];
        }

        $serials = [];

        foreach (array_keys((array) $channels) as $name) {
            $serials[substr((string) $name, strlen(self::CHANNEL_PREFIX))] = true;
        }

        return $serials;
    }

    /**
     * Client Pusher trỏ vào Reverb, lấy thông số từ config broadcasting của Laravel.
     */
    private function pusher(): Pusher
    {
        /** @var array<string, mixed> $config */
        $config = config('broadcasting.connections.reverb');

        /** @var array<string, mixed> $options */
        $options = $config['options'] ?? [];

        return new Pusher(
            (string) $config['key'],
            (string) $config['secret'],
            (string) $config['app_id'],
            [
                'host' => (string) ($options['host'] ?? '127.0.0.1'),
                'port' => (int) ($options['port'] ?? 8080),
                'scheme' => (string) ($options['scheme'] ?? 'http'),
                'useTLS' => ($options['useTLS'] ?? false) === true,
                'timeout' => 5,
            ],
        );
    }
}
