<?php

use App\Enums\AlertType;
use App\Models\Device;
use App\Models\DeviceFcmToken;
use App\Models\User;
use App\Notifications\DeviceAlertNotification;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\Fcm\FcmChannel;

/*
|--------------------------------------------------------------------------
| Thông báo: Notification (database + broadcast + FCM), token FCM, ngưỡng cảnh báo
|--------------------------------------------------------------------------
*/

function sendAlertTo(User $user, string $serial = '4313800597'): void
{
    // Chỉ channel database: FcmChannel cần credentials Firebase thật.
    Notification::sendNow($user, new DeviceAlertNotification(AlertType::Fault, $serial, 'Biến tần báo lỗi', 'Kiểm tra thiết bị.'), ['database']);
}

test('một thông báo đi qua database, broadcast và FCM', function () {
    $notification = new DeviceAlertNotification(AlertType::LowBattery, '4313800597', 'Pin thấp', 'Còn 12%');

    expect($notification->via(new User))->toBe(['database', 'broadcast', FcmChannel::class])
        ->and($notification->toArray(new User))->toMatchArray([
            'device_serial' => '4313800597',
            'type' => 'low_battery',
            'severity' => 'warning',
        ])
        ->and($notification->toFcm(new User)->data)->toMatchArray([
            'device_serial' => '4313800597',
            'type' => 'low_battery',
        ]);
});

test('user chỉ xác thực được kênh thông báo của chính mình', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-App.Models.User.{$user->id}",
        ])
        ->assertSuccessful()
        ->assertJsonStructure(['auth']);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-App.Models.User.{$other->id}",
        ])
        ->assertForbidden();
});

test('đăng ký token FCM và chuyển token sang user mới khi đổi tài khoản trên cùng máy', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();

    $this->actingAs($first, 'sanctum')
        ->postJson('/api/v1/fcm/tokens', ['fcm_token' => 'token-abc', 'device_name' => 'Pixel 8', 'platform' => 'android'])
        ->assertCreated();

    $this->actingAs($second, 'sanctum')
        ->postJson('/api/v1/fcm/tokens', ['fcm_token' => 'token-abc'])
        ->assertCreated();

    expect(DeviceFcmToken::query()->count())->toBe(1)
        ->and(DeviceFcmToken::query()->first()->user_id)->toBe($second->id)
        ->and($second->routeNotificationForFcm())->toBe(['token-abc'])
        ->and($first->routeNotificationForFcm())->toBe([]);
});

test('đăng ký token FCM kiểm tra dữ liệu đầu vào', function () {
    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson('/api/v1/fcm/tokens', ['platform' => 'windows'])
        ->assertInvalid(['fcm_token', 'platform']);
});

test('huỷ token FCM chỉ xoá token của chính user', function () {
    $me = User::factory()->create();
    $mine = DeviceFcmToken::factory()->create(['user_id' => $me->id]);
    $theirs = DeviceFcmToken::factory()->create();

    $this->actingAs($me, 'sanctum')
        ->deleteJson('/api/v1/fcm/tokens', ['fcm_token' => $theirs->fcm_token])
        ->assertNoContent();

    expect(DeviceFcmToken::query()->whereKey($theirs->id)->exists())->toBeTrue();

    $this->actingAs($me, 'sanctum')
        ->deleteJson('/api/v1/fcm/tokens', ['fcm_token' => $mine->fcm_token])
        ->assertNoContent();

    expect(DeviceFcmToken::query()->whereKey($mine->id)->exists())->toBeFalse();
});

test('danh sách thông báo chỉ gồm của mình, kèm số chưa đọc', function () {
    $me = User::factory()->create();
    sendAlertTo($me);
    sendAlertTo($me, '9999999999');
    sendAlertTo(User::factory()->create());

    $this->actingAs($me, 'sanctum')
        ->getJson('/api/v1/notifications')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('unread_count', 2)
        ->assertJsonPath('data.0.type', 'fault')
        ->assertJsonPath('data.0.read_at', null);
});

test('đánh dấu đã đọc một thông báo và tất cả', function () {
    $me = User::factory()->create();
    sendAlertTo($me);
    sendAlertTo($me);

    $id = $me->notifications()->first()->id;

    $this->actingAs($me, 'sanctum')
        ->patchJson("/api/v1/notifications/{$id}/read")
        ->assertSuccessful();

    expect($me->unreadNotifications()->count())->toBe(1);

    $this->actingAs($me, 'sanctum')
        ->postJson('/api/v1/notifications/mark-all-read')
        ->assertSuccessful();

    expect($me->unreadNotifications()->count())->toBe(0);
});

test('không đánh dấu đã đọc thông báo của người khác', function () {
    $owner = User::factory()->create();
    sendAlertTo($owner);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->patchJson('/api/v1/notifications/'.$owner->notifications()->first()->id.'/read')
        ->assertNotFound();

    expect($owner->unreadNotifications()->count())->toBe(1);
});

test('ngưỡng cảnh báo trả về mặc định khi chưa cấu hình và lưu được thay đổi', function () {
    $owner = User::factory()->create();
    $device = Device::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/devices/{$device->serial}/alert-settings")
        ->assertSuccessful()
        ->assertJsonPath('data.battery_alert_mode', 'soc')
        ->assertJsonPath('data.low_battery_soc', 20)
        ->assertJsonPath('data.low_battery_voltage', 48)
        ->assertJsonPath('data.notify_grid', true)
        ->assertJsonPath('data.notify_offline', true);

    $this->actingAs($owner, 'sanctum')
        ->putJson("/api/v1/devices/{$device->serial}/alert-settings", [
            'battery_alert_mode' => 'voltage',
            'low_battery_voltage' => 52.2,
            'notify_grid' => false,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.battery_alert_mode', 'voltage')
        ->assertJsonPath('data.low_battery_voltage', 52.2)
        ->assertJsonPath('data.notify_grid', false)
        ->assertJsonPath('data.notify_fault', true);

    expect($device->alertSettingOrDefault()->fresh()->battery_alert_mode)->toBe('voltage')
        ->and($device->alertSettingOrDefault()->fresh()->low_battery_voltage)->toBe(52.2);
});

test('ngưỡng cảnh báo nằm ngoài khoảng cho phép bị chặn', function () {
    $owner = User::factory()->create();
    $device = Device::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner, 'sanctum')
        ->putJson("/api/v1/devices/{$device->serial}/alert-settings", ['low_battery_soc' => 99])
        ->assertInvalid(['low_battery_soc']);
});

test('người lạ không đọc hay sửa được ngưỡng cảnh báo', function () {
    $device = Device::factory()->create(['owner_id' => User::factory()]);
    $stranger = User::factory()->create();

    $this->actingAs($stranger, 'sanctum')
        ->getJson("/api/v1/devices/{$device->serial}/alert-settings")
        ->assertForbidden();

    $this->actingAs($stranger, 'sanctum')
        ->putJson("/api/v1/devices/{$device->serial}/alert-settings", ['low_battery_soc' => 20])
        ->assertForbidden();
});
