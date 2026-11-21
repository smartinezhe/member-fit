<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Por defecto crea un plan mensual activo (30 días, 80.000 COP).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => null,
            'duration_days' => 30,
            'price' => 80000,
            'is_active' => true,
        ];
    }

    /**
     * Plan desactivado: no se ofrece en nuevas membresías (RN-12).
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
