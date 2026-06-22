<?php

namespace Database\Factories;

use App\Enums\PushPlatform;
use App\Models\PushDevice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PushDevice> */
class PushDeviceFactory extends Factory
{
    protected $model = PushDevice::class;

    public function definition(): array
    {
        $token = 'fcm-'.Str::random(120);

        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => fn (array $attributes) => User::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'platform' => PushPlatform::Android,
            'token' => $token,
            'token_hash' => hash('sha256', $token),
            'device_uuid' => fake()->uuid(),
            'device_name' => fake()->randomElement(['Pixel', 'Samsung', 'Motorola']),
            'app_version' => '1.0.0',
            'last_seen_at' => now(),
        ];
    }
}
