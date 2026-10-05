<?php

use App\Http\Resources\DeviceResource;
use App\Models\Device;
use App\Models\User;
use App\Services\InfluxService;
use App\Services\RealtimeService;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API thiết bị — CRUD, luồng claim, và phân quyền theo DevicePolicy
|--------------------------------------------------------------------------
*/

test('user thường chỉ thấy thiết bị của mình', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();

    $mine = Device::factory()->create(['owner_id' => $me->id, 'name' => 'Của tôi']);
    Device::factory()->create(['owner_id' => $other->id, 'name' => 'Của người khác']);
    Device::factory()->create(['name' => 'Chưa gắn']);

    $this->actingAs($me, 'sanctum')
        ->getJson('/api/v1/devices')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.serial', $mine->serial);
});

test('admin thấy tất cả thiết bị kèm chủ sở hữu', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Device::factory()->count(3)->create();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/devices')
        ->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('tìm kiếm theo serial hoặc tên', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Device::factory()->create(['serial' => '4313800597', 'name' => 'Lux SNA 6kw']);
    Device::factory()->create(['serial' => '9999999999', 'name' => 'Khác']);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/devices?search=4313800')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/devices?search=Lux')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

test('lọc theo trạng thái đã gắn chủ', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Device::factory()->create(['owner_id' => User::factory()]);
    Device::factory()->create(['owner_id' => null]);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/devices?claimed=1')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.is_claimed', true);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/devices?claimed=0')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.is_claimed', false);
});

test('cột sắp xếp ngoài whitelist không gây lỗi SQL', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Device::factory()->create(['name' => 'B']);
    Device::factory()->create(['name' => 'A']);

    // Tên cột lạ → rơi về sort=name, chiều mặc định là asc.
    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/devices?sort=owner_id%3Bdrop%20table')
        ->assertSuccessful()
        ->assertJsonPath('data.0.name', 'A');

    // Chiều sắp xếp do client quyết định, độc lập với cột.
    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/devices?sort=name&direction=desc')
        ->assertSuccessful()
        ->assertJsonPath('data.0.name', 'B');
});

test('chủ sở hữu xem được chi tiết, người khác bị 403', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $device = Device::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/devices/{$device->serial}")
        ->assertSuccessful()
        ->assertJsonPath('data.serial', $device->serial);

    $this->actingAs($stranger, 'sanctum')
        ->getJson("/api/v1/devices/{$device->serial}")
        ->assertForbidden();
});

test('thiết bị không tồn tại trả 404', function () {
    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/devices/0000000000')
        ->assertNotFound();
});

test('chỉ admin tạo được thiết bị', function () {
    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson('/api/v1/devices', ['serial' => '1234567890'])
        ->assertForbidden();

    $this->actingAs(User::factory()->create(['is_admin' => true]), 'sanctum')
        ->postJson('/api/v1/devices', ['serial' => '1234567890', 'name' => 'Mới'])
        ->assertCreated()
        ->assertJsonPath('data.serial', '1234567890')
        ->assertJsonPath('data.is_claimed', false);
});

test('tạo thiết bị trùng serial bị chặn', function () {
    Device::factory()->create(['serial' => '1234567890']);

    $this->actingAs(User::factory()->create(['is_admin' => true]), 'sanctum')
        ->postJson('/api/v1/devices', ['serial' => '1234567890'])
        ->assertInvalid(['serial']);
});

test('chủ sở hữu đổi tên và bật/tắt thiết bị của mình', function () {
    $owner = User::factory()->create();
    $device = Device::factory()->create(['owner_id' => $owner->id, 'name' => 'Cũ', 'enabled' => true]);

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/devices/{$device->serial}", ['name' => 'Mới', 'enabled' => false])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Mới')
        ->assertJsonPath('data.enabled', false);

    expect($device->fresh()->enabled)->toBeFalse();
});

test('người không phải chủ sở hữu không sửa được thiết bị', function () {
    $device = Device::factory()->create(['owner_id' => User::factory()]);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->patchJson("/api/v1/devices/{$device->serial}", ['name' => 'Đổi trộm'])
        ->assertForbidden();
});

test('user thường không được đổi chủ sở hữu thiết bị', function () {
    $owner = User::factory()->create();
    $device = Device::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/v1/devices/{$device->serial}", ['owner_id' => User::factory()->create()->id])
        ->assertInvalid(['owner_id']);

    expect($device->fresh()->owner_id)->toBe($owner->id);
});

test('admin gán được chủ sở hữu khác', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $device = Device::factory()->create(['owner_id' => null]);
    $target = User::factory()->create();

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/v1/devices/{$device->serial}", ['owner_id' => $target->id])
        ->assertSuccessful();

    expect($device->fresh()->owner_id)->toBe($target->id);
});

test('chỉ admin xoá được thiết bị', function () {
    $owner = User::factory()->create();
    $device = Device::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/devices/{$device->serial}")
        ->assertForbidden();

    $this->actingAs(User::factory()->create(['is_admin' => true]), 'sanctum')
        ->deleteJson("/api/v1/devices/{$device->serial}")
        ->assertNoContent();

    expect(Device::query()->where('serial', $device->serial)->exists())->toBeFalse();
});

test('claim gắn thiết bị vào tài khoản khi đúng mã', function () {
    $user = User::factory()->create();
    $device = Device::factory()->create(['device_code' => 'ABCD1234', 'owner_id' => null]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/devices/claim', [
            'serial' => $device->serial,
            'device_code' => 'ABCD1234',
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_claimed', true);

    $device->refresh();
    expect($device->owner_id)->toBe($user->id)
        ->and($device->verified_at)->not->toBeNull();
});

test('claim sai mã bị chặn và không gắn chủ', function () {
    $device = Device::factory()->create(['device_code' => 'ABCD1234', 'owner_id' => null]);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson('/api/v1/devices/claim', [
            'serial' => $device->serial,
            'device_code' => 'SAI-CODE',
        ])
        ->assertStatus(422);

    expect($device->fresh()->owner_id)->toBeNull();
});

test('claim thiết bị đã thuộc tài khoản khác trả 409', function () {
    $device = Device::factory()->create([
        'device_code' => 'ABCD1234',
        'owner_id' => User::factory(),
    ]);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson('/api/v1/devices/claim', [
            'serial' => $device->serial,
            'device_code' => 'ABCD1234',
        ])
        ->assertStatus(409);
});

test('claim thiết bị đã thuộc chính mình trả 409', function () {
    $user = User::factory()->create();
    $device = Device::factory()->create(['device_code' => 'ABCD1234', 'owner_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/devices/claim', [
            'serial' => $device->serial,
            'device_code' => 'ABCD1234',
        ])
        ->assertStatus(409);
});

test('realtime trả ảnh chụp từ RealtimeService cho chủ sở hữu', function () {
    $owner = User::factory()->create();
    $device = Device::factory()->create(['owner_id' => $owner->id]);

    $this->mock(RealtimeService::class)
        ->shouldReceive('snapshot')
        ->once()
        ->with($device->serial)
        ->andReturn([
            'online' => true,
            'latest' => ['pv_power' => 1648],
            'timestamp' => '14:58:32 21/09/2026',
        ]);

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/devices/{$device->serial}/realtime")
        ->assertSuccessful()
        ->assertJsonPath('data.online', true)
        ->assertJsonPath('data.latest.pv_power', 1648)
        ->assertJsonPath('data.timestamp', '14:58:32 21/09/2026');
});

test('realtime và history chặn người không sở hữu', function () {
    $device = Device::factory()->create(['owner_id' => User::factory()]);
    $stranger = User::factory()->create();

    $this->actingAs($stranger, 'sanctum')
        ->getJson("/api/v1/devices/{$device->serial}/realtime")
        ->assertForbidden();

    $this->actingAs($stranger, 'sanctum')
        ->getJson("/api/v1/devices/{$device->serial}/history?field=pv_power")
        ->assertForbidden();
});

test('history trả dữ liệu từ InfluxService cho chủ sở hữu', function () {
    $owner = User::factory()->create();
    $device = Device::factory()->create(['owner_id' => $owner->id]);

    $this->mock(InfluxService::class)
        ->shouldReceive('history')
        ->once()
        ->andReturn([['time' => '2026-09-21T00:00:00Z', 'value' => 1.5]]);

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/devices/{$device->serial}/history?field=pv_power&window=10m")
        ->assertSuccessful()
        ->assertJsonPath('data.0.value', 1.5);
});

test('device_code chỉ lộ cho admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();
    $device = Device::factory()->create(['owner_id' => $owner->id, 'device_code' => 'SECRET12']);

    $viaAdmin = Request::create('/');
    $viaAdmin->setUserResolver(fn () => $admin);

    $viaOwner = Request::create('/');
    $viaOwner->setUserResolver(fn () => $owner);

    expect(DeviceResource::make($device->load('owner'))->resolve($viaAdmin))
        ->toHaveKey('device_code', 'SECRET12')
        ->and(DeviceResource::make($device->load('owner'))->resolve($viaOwner))
        ->not->toHaveKey('device_code');
});

test('chủ sở hữu huỷ theo dõi (unclaim) thiết bị', function () {
    $owner = User::factory()->create();
    $device = Device::factory()->create(['owner_id' => $owner->id, 'verified_at' => now()]);

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/devices/{$device->serial}/unclaim")
        ->assertSuccessful()
        ->assertJsonPath('message', 'Đã hủy theo dõi thiết bị.');

    $device->refresh();
    expect($device->owner_id)->toBeNull()
        ->and($device->verified_at)->toBeNull();
});

test('người lạ không được unclaim thiết bị', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $device = Device::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($stranger, 'sanctum')
        ->postJson("/api/v1/devices/{$device->serial}/unclaim")
        ->assertForbidden();

    expect($device->fresh()->owner_id)->toBe($owner->id);
});
