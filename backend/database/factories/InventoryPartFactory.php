<?php

namespace Database\Factories;

use App\Models\TechnicianProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryPartFactory extends Factory
{
    public function definition(): array
    {
        return ['technician_id' => TechnicianProfile::factory(), 'sku' => fake()->unique()->bothify('PART-####??'), 'name' => 'Replacement battery', 'quantity' => 5, 'reorder_level' => 2, 'unit_cost' => '450.00', 'active' => true];
    }
}
