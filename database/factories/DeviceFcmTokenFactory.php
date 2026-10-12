<?php

namespace Database\Factories;

use App\Models\DeviceFcmToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceFcmToken>
 */
class DeviceFcmTokenFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'fcm_token' => $this->faker->unique()->sha256(),
            'device_name' => $this->faker->randomElement(['iPhone 15', 'Pixel 8', 'Galaxy S24']),
            'platform' => $this->faker->randomElement(['ios', 'android']),
        ];
    }
}
