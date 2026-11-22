<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Member;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    /**
     * Por defecto crea una membresía de 30 días que empieza hoy (vigente).
     * Las pruebas de borde sobrescriben starts_on y ends_on.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'plan_id' => Plan::factory(),
            'created_by' => User::factory(),
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addDays(29)->toDateString(),
        ];
    }

    /**
     * Membresía anulada (RN-08).
     */
    public function cancelled(string $reason = 'Registro por error'): static
    {
        return $this->state(fn (array $attributes) => [
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }
}
