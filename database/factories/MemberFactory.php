<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Enums\Role;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    /**
     * Por defecto crea un socio ficticio con cédula y celular colombiano
     * (10 dígitos que empiezan por 3), sin entrenador asignado.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_type' => DocumentType::CC,
            'document_number' => fake()->unique()->numerify('##########'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->numerify('3#########'),
            'email' => null,
            'birth_date' => null,
            'emergency_contact_name' => null,
            'emergency_contact_phone' => null,
            'notes' => null,
            'trainer_id' => null,
        ];
    }

    /**
     * Socio con entrenador asignado (RN-13): usuario activo con rol Entrenador.
     */
    public function withTrainer(?User $trainer = null): static
    {
        return $this->state(fn (array $attributes) => [
            'trainer_id' => $trainer?->id ?? User::factory()->state([
                'role' => Role::Trainer,
                'is_active' => true,
            ]),
        ]);
    }
}
