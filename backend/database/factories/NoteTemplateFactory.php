<?php

namespace Database\Factories;

use App\Models\TechnicianProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class NoteTemplateFactory extends Factory
{
    public function definition(): array
    {
        return ['technician_id' => TechnicianProfile::factory(), 'title' => fake()->unique()->sentence(3), 'body' => 'Device inspected. Waiting for customer approval before replacing parts.'];
    }
}
