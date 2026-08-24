<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Đảm bảo có tài khoản admin.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@luxmonitor.local'],
            [
                'name' => 'Admin',
                'username' => 'admin',
                'password' => '66668888',
                'is_admin' => true,
                'is_active' => true,
            ],
        );
    }
}
