<?php

namespace Database\Factories;

use App\Models\ScheduleBlock;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ScheduleBlock> */
class ScheduleBlockFactory extends Factory
{
    protected $model = ScheduleBlock::class;

    public function definition(): array
    {
        $startsAt = now()->addWeek()->startOfHour();

        return [
            'tenant_id' => Tenant::factory(),
            'doctor_user_id' => User::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'reason' => fake()->sentence(),
        ];
    }
}
