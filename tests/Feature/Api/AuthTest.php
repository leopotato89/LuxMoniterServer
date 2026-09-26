<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

/*
|--------------------------------------------------------------------------
| Xác thực qua API (Sanctum token) — dùng chung cho SPA và mobile
|--------------------------------------------------------------------------
*/

test('đăng nhập được bằng email', function () {
    $user = User::factory()->create([
        'email' => 'user@luxmonitor.local',
        'password' => 'secret123',
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'login' => 'user@luxmonitor.local',
        'password' => 'secret123',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'username', 'email', 'is_admin', 'is_active']]]);
});

test('đăng nhập được bằng username', function () {
    $user = User::factory()->create([
        'username' => 'giang',
        'password' => 'secret123',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'login' => 'giang',
        'password' => 'secret123',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.user.id', $user->id);
});

test('sai mật khẩu trả 422 và không lộ user nào tồn tại', function () {
    User::factory()->create(['username' => 'giang', 'password' => 'secret123']);

    $this->postJson('/api/v1/auth/login', [
        'login' => 'giang',
        'password' => 'sai-mat-khau',
    ])->assertInvalid(['login']);

    $this->postJson('/api/v1/auth/login', [
        'login' => 'khong-ton-tai',
        'password' => 'secret123',
    ])->assertInvalid(['login']);
});

test('tài khoản bị đình chỉ không đăng nhập được', function () {
    User::factory()->create([
        'username' => 'bi-khoa',
        'password' => 'secret123',
        'is_active' => false,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'login' => 'bi-khoa',
        'password' => 'secret123',
    ])->assertForbidden();
});

test('đăng ký tạo user mới và trả token', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Nguyễn Văn A',
        'username' => 'nguyenvana',
        'email' => 'a@luxmonitor.local',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.user.username', 'nguyenvana')
        ->assertJsonPath('data.user.is_admin', false);

    expect(User::query()->where('username', 'nguyenvana')->exists())->toBeTrue();

    // Mật khẩu phải được hash (cast 'hashed' trên model).
    $user = User::query()->where('username', 'nguyenvana')->first();
    expect($user->password)->not->toBe('secret123');
});

test('đăng ký trùng username hoặc email bị chặn', function () {
    User::factory()->create(['username' => 'dadung', 'email' => 'dadung@luxmonitor.local']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Trùng',
        'username' => 'dadung',
        'email' => 'khac@luxmonitor.local',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ])->assertInvalid(['username']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Trùng',
        'username' => 'khac',
        'email' => 'dadung@luxmonitor.local',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ])->assertInvalid(['email']);
});

test('đăng ký thiếu xác nhận mật khẩu bị chặn', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Thiếu',
        'username' => 'thieu',
        'email' => 'thieu@luxmonitor.local',
        'password' => 'secret123',
        'password_confirmation' => 'khac-nhau',
    ])->assertInvalid(['password']);
});

test('/auth/me không có token trả 401', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

test('/auth/me trả user đang đăng nhập', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/auth/me')
        ->assertSuccessful()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonMissingPath('data.password');
});

test('đăng xuất thu hồi token hiện tại', function () {
    $user = User::factory()->create(['username' => 'giang', 'password' => 'secret123']);

    $token = $this->postJson('/api/v1/auth/login', [
        'login' => 'giang',
        'password' => 'secret123',
    ])->json('data.token');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    expect(PersonalAccessToken::query()->count())->toBe(0);

    // Test harness dùng chung container cho mọi request trong cùng một test, nên guard
    // Sanctum vẫn cache user của request trước đó. Xoá cache để request sau là request mới.
    $this->app['auth']->forgetGuards();

    // Token đã bị xoá khỏi DB nên không dùng lại được.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();
});

test('endpoint đăng nhập bị giới hạn tần suất', function () {
    foreach (range(1, 5) as $ignored) {
        $this->postJson('/api/v1/auth/login', [
            'login' => 'giang',
            'password' => 'sai',
        ])->assertInvalid(['login']);
    }

    $this->postJson('/api/v1/auth/login', [
        'login' => 'giang',
        'password' => 'sai',
    ])->assertStatus(429);
});
