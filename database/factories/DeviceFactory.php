<?php

namespace Database\Factories;

use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'serial' => $this->faker->unique()->numerify('##########'),
            'name' => $this->faker->words(2, true),
            'owner_id' => null,
            'device_code' => strtoupper($this->faker->bothify('########')),
            'verified_at' => null,
            'enabled' => true,
        ];
    }
}
