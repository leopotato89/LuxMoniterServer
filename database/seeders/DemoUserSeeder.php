<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    /**
     * Tài khoản user demo (không phải admin).
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'user@luxmonitor.local'],
            [
                'name' => 'User Demo',
                'password' => 'password',
                'is_admin' => false,
                'is_active' => true,
            ],
        );
    }
}
