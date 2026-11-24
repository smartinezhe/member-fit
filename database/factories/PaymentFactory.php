<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Membership;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Por defecto crea un pago en efectivo de 80.000 COP, registrado ahora,
     * para una membresía nueva.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'membership_id' => Membership::factory(),
            'amount' => 80000,
            'method' => PaymentMethod::Cash,
            'paid_at' => now(),
        ];
    }
}
