<?php

namespace Database\Factories;

use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TechnicianProfile> */
class TechnicianProfileFactory extends Factory
{
    protected $model = TechnicianProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'technician']),
            'biography' => 'A test technician providing careful diagnosis, clear estimates and device repairs.',
            'years_experience' => fake()->numberBetween(1, 15),
            'city' => 'Santa Cruz',
            'province' => 'Laguna',
            'availability' => 'available',
            'verification_status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['verification_status' => 'approved', 'verified_at' => now()]);
    }
}
