<?php

use App\Enums\AlertType;
use App\Models\Device;
use App\Models\DeviceAlertSetting;
use App\Models\User;
use App\Notifications\DeviceAlertNotification;
use App\Services\AlertDetectionService;
use App\Services\RealtimeService;
use Illuminate\Support\Facades\Notification;

/*
|--------------------------------------------------------------------------
| Phát hiện cảnh báo: điều kiện, cooldown, hysteresis, khôi phục
|--------------------------------------------------------------------------
| Redis không có trong test nên RealtimeService được thay bằng bản đọc từ biến local.
*/

/**
 * @param  array<string, mixed>|null  $latest
 * @return array{0: Device, 1: User, 2: callable}
 */
function deviceWithTelemetry(?array &$latest, bool &$online, array $setting = []): array
{
    $owner = User::factory()->create();
    $device = Device::factory()->create(['owner_id' => $owner->id]);

    if ($setting !== []) {
        DeviceAlertSetting::factory()->create(['device_id' => $device->id, ...$setting]);
    }

    test()->mock(RealtimeService::class, function ($mock) use (&$latest, &$online) {
        $mock->shouldReceive('latest')->andReturnUsing(function () use (&$latest) {
            return $latest;
        });
        $mock->shouldReceive('online')->andReturnUsing(function () use (&$online) {
            return $online;
        });
    });

    $check = fn () => app(AlertDetectionService::class)->check($device->fresh(['owner', 'alertSetting']));

    return [$device, $owner, $check];
}

function sentTypes(User $owner): array
{
    return Notification::sent($owner, DeviceAlertNotification::class)
        ->map(fn (DeviceAlertNotification $n) => $n->type)
        ->all();
}

beforeEach(fn () => Notification::fake());

test('pin yếu theo SOC nấc 5% không báo lại ở cùng mức trong 1 tiếng', function () {
    $latest = ['battery_soc' => 30, 'discharge_power' => 800, 'grid_voltage' => 220];
    $online = true;
    [, $owner, $check] = deviceWithTelemetry($latest, $online, ['battery_alert_mode' => 'soc', 'low_battery_soc' => 30]);

    $check();
    // Vẫn ở mức 30%, quét lại không gửi thêm
    $check();

    expect(sentTypes($owner))->toBe([AlertType::LowBattery]);

    Notification::assertSentTo($owner, DeviceAlertNotification::class, function ($n) {
        return str_contains($n->body, 'Pin lưu trữ còn lại 30%. Hãy chú ý tải tiêu thụ.');
    });

    // Tụt xuống 25% (nấc 5% tiếp theo) -> gửi cảnh báo mới
    $latest['battery_soc'] = 25;
    $check();

    expect(sentTypes($owner))->toBe([AlertType::LowBattery, AlertType::LowBattery]);
});

test('pin yếu theo Voltage nấc 0.2V không báo lại ở cùng mức trong 1 tiếng', function () {
    $latest = ['battery_voltage' => 52.2, 'discharge_power' => 800, 'grid_voltage' => 220];
    $online = true;
    [, $owner, $check] = deviceWithTelemetry($latest, $online, [
        'battery_alert_mode' => 'voltage',
        'low_battery_voltage' => 52.2,
    ]);

    $check();
    // Quét lại cùng mức 52.2V -> không gửi thêm
    $check();

    expect(sentTypes($owner))->toBe([AlertType::LowBattery]);

    Notification::assertSentTo($owner, DeviceAlertNotification::class, function ($n) {
        return str_contains($n->body, 'Điện áp pin đang ở mức thấp (52.2 Vol). Hãy chú ý tải tiêu thụ.');
    });

    // Tụt xuống 52.0V (nấc 0.2V tiếp theo) -> gửi cảnh báo mới
    $latest['battery_voltage'] = 52.0;
    $check();

    expect(sentTypes($owner))->toBe([AlertType::LowBattery, AlertType::LowBattery]);
});

test('pin thấp theo Voltage nhưng đang sạc thì không cảnh báo', function () {
    $latest = ['battery_voltage' => 47.0, 'discharge_power' => 0, 'charge_power' => 1000, 'grid_voltage' => 220];
    $online = true;
    [, $owner, $check] = deviceWithTelemetry($latest, $online, [
        'battery_alert_mode' => 'voltage',
        'low_battery_voltage' => 48.0,
    ]);

    $check();

    expect(sentTypes($owner))->toBe([]);
});

test('pin hồi phục theo Voltage khi vượt ngưỡng cộng 0.5V', function () {
    $latest = ['battery_voltage' => 48.0, 'discharge_power' => 800, 'grid_voltage' => 220];
    $online = true;
    [, $owner, $check] = deviceWithTelemetry($latest, $online, [
        'battery_alert_mode' => 'voltage',
        'low_battery_voltage' => 48.0,
    ]);

    $check();
    expect(sentTypes($owner))->toBe([AlertType::LowBattery]);

    // Lên 48.3V (chưa đủ +0.5V) -> chưa hồi phục
    $latest['battery_voltage'] = 48.3;
    $check();
    expect(sentTypes($owner))->toBe([AlertType::LowBattery]);

    // Lên 48.6V -> hồi phục!
    $latest['battery_voltage'] = 48.6;
    $check();
    expect(sentTypes($owner))->toBe([AlertType::LowBattery, AlertType::Recovery]);
});

test('pin hồi phục theo SOC khi vượt ngưỡng cộng 5%', function () {
    $latest = ['battery_soc' => 20, 'discharge_power' => 800, 'grid_voltage' => 220];
    $online = true;
    [, $owner, $check] = deviceWithTelemetry($latest, $online, ['low_battery_soc' => 20]);

    $check();
    expect(sentTypes($owner))->toBe([AlertType::LowBattery]);

    // 23% chưa đủ 20 + 5 -> chưa hồi phục
    $latest['battery_soc'] = 23;
    $check();
    expect(sentTypes($owner))->toBe([AlertType::LowBattery]);

    // 26% -> hồi phục!
    $latest['battery_soc'] = 26;
    $check();
    expect(sentTypes($owner))->toBe([AlertType::LowBattery, AlertType::Recovery]);
});

test('mất điện lưới gửi cảnh báo và có điện lưới lại gửi khôi phục', function () {
    $latest = ['grid_voltage' => 0.0, 'battery_soc' => 80, 'state' => 4];
    $online = true;
    [, $owner, $check] = deviceWithTelemetry($latest, $online);

    $check();

    Notification::assertSentTo($owner, DeviceAlertNotification::class, function ($n) {
        return $n->type === AlertType::GridLost && $n->title === 'Mất điện lưới';
    });

    // Có điện lưới lại (>= 180V)
    $latest['grid_voltage'] = 224.5;
    $check();

    Notification::assertSentTo($owner, DeviceAlertNotification::class, function ($n) {
        return $n->type === AlertType::GridRestored && $n->title === 'Đã có điện lưới';
    });

    expect(sentTypes($owner))->toBe([AlertType::GridLost, AlertType::GridRestored]);
});

test('tắt notify_grid thì không cảnh báo mất lưới', function () {
    $latest = ['grid_voltage' => 0.0, 'battery_soc' => 80, 'state' => 4];
    $online = true;
    [, $owner, $check] = deviceWithTelemetry($latest, $online, ['notify_grid' => false]);

    $check();

    expect(sentTypes($owner))->toBe([]);
});

test('lỗi biến tần (state = Fault) gửi cảnh báo nghiêm trọng', function () {
    $latest = ['battery_soc' => 80, 'discharge_power' => 0, 'state' => 1, 'grid_voltage' => 220];
    $online = true;
    [, $owner, $check] = deviceWithTelemetry($latest, $online);

    $check();

    Notification::assertSentTo($owner, DeviceAlertNotification::class, fn ($n) => $n->type === AlertType::Fault
        && $n->type->severity()->value === 'critical');
});

test('mã lỗi hoặc mã cảnh báo khác 0 cũng được coi là lỗi', function () {
    $latest = ['battery_soc' => 80, 'state' => 4, 'warning_code' => 12, 'grid_voltage' => 220];
    $online = true;
    [, $owner, $check] = deviceWithTelemetry($latest, $online);

    $check();

    expect(sentTypes($owner))->toBe([AlertType::Fault]);
});

test('tắt notify_fault thì không cảnh báo lỗi', function () {
    $latest = ['battery_soc' => 80, 'state' => 1, 'grid_voltage' => 220];
    $online = true;
    [, $owner, $check] = deviceWithTelemetry($latest, $online, ['notify_fault' => false]);

    $check();

    expect(sentTypes($owner))->toBe([]);
});

test('mất kết nối gửi cảnh báo, online lại gửi khôi phục', function () {
    $latest = ['battery_soc' => 80, 'state' => 4, 'grid_voltage' => 220];
    $online = false;
    [, $owner, $check] = deviceWithTelemetry($latest, $online);

    $check();
    $online = true;
    $check();

    expect(sentTypes($owner))->toBe([AlertType::Offline, AlertType::Recovery]);
});

test('tắt notify_recovery thì chỉ có cảnh báo, không có thông báo khôi phục', function () {
    $latest = ['battery_soc' => 80, 'state' => 4, 'grid_voltage' => 220];
    $online = false;
    [, $owner, $check] = deviceWithTelemetry($latest, $online, ['notify_recovery' => false]);

    $check();
    $online = true;
    $check();

    expect(sentTypes($owner))->toBe([AlertType::Offline]);
});

test('lỗi nhấp nháy trong thời gian cooldown chỉ cảnh báo một lần', function () {
    $latest = ['battery_soc' => 80, 'state' => 1, 'grid_voltage' => 220];
    $online = true;
    [, $owner, $check] = deviceWithTelemetry($latest, $online);

    $check();
    $latest['state'] = 4;
    $check();
    $latest['state'] = 1;
    $check();
    $latest['state'] = 4;
    $check();

    // Cảnh báo 1 lần; lần lỗi thứ hai bị cooldown chặn nên không có "khôi phục" mồ côi.
    expect(sentTypes($owner))->toBe([AlertType::Fault, AlertType::Recovery]);
});

test('thiết bị chưa có chủ hoặc đã tắt thì không cảnh báo', function () {
    $latest = ['battery_soc' => 5, 'discharge_power' => 800, 'state' => 1];
    $online = false;
    [$device, $owner, $check] = deviceWithTelemetry($latest, $online);

    $device->update(['enabled' => false]);
    $check();

    $device->update(['enabled' => true, 'owner_id' => null]);
    $check();

    expect(sentTypes($owner))->toBe([]);
});

test('thiết bị không có telemetry vẫn cảnh báo mất kết nối nhưng không cảnh báo pin hay lỗi', function () {
    $latest = null;
    $online = false;
    [, $owner, $check] = deviceWithTelemetry($latest, $online);

    $check();

    expect(sentTypes($owner))->toBe([AlertType::Offline]);
});
