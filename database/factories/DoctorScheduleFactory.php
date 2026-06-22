<?php

namespace Database\Factories;

use App\Models\DoctorSchedule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DoctorSchedule> */
class DoctorScheduleFactory extends Factory
{
    protected $model = DoctorSchedule::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'doctor_user_id' => User::factory(),
            'weekday' => fake()->numberBetween(1, 7),
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
            'is_active' => true,
        ];
    }
}
