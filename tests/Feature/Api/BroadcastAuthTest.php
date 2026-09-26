<?php

use App\Models\Device;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Xác thực kênh broadcast (WebSocket)
|--------------------------------------------------------------------------
| Endpoint dùng chung cho SPA và mobile: Bearer token qua middleware auth:sanctum.
*/

test('chủ sở hữu xác thực được kênh thiết bị của mình', function () {
    $user = User::factory()->create();
    $device = Device::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-device.'.$device->serial,
        ])
        ->assertSuccessful()
        ->assertJsonStructure(['auth']);
});

test('admin xác thực được kênh của thiết bị bất kỳ', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $device = Device::factory()->create();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-device.'.$device->serial,
        ])
        ->assertSuccessful()
        ->assertJsonStructure(['auth']);
});

test('người khác không xác thực được kênh thiết bị', function () {
    $stranger = User::factory()->create();
    $device = Device::factory()->create();

    $this->actingAs($stranger, 'sanctum')
        ->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-device.'.$device->serial,
        ])
        ->assertForbidden();
});

test('thiết bị không tồn tại thì bị từ chối', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-device.9999999999',
        ])
        ->assertForbidden();
});

test('không có token thì bị từ chối', function () {
    $device = Device::factory()->create();

    $this->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-device.'.$device->serial,
    ])->assertUnauthorized();
});
