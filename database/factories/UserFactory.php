<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Por defecto crea un recepcionista activo y con 2FA por correo activado,
     * igual que un usuario real del panel (el 2FA es obligatorio, RF-04). Los
     * estados de abajo cambian el rol o el estado para las pruebas.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::Receptionist,
            'phone' => fake()->numerify('3#########'),
            'is_active' => true,
            'has_email_authentication' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['role' => Role::Admin]);
    }

    public function receptionist(): static
    {
        return $this->state(fn (array $attributes) => ['role' => Role::Receptionist]);
    }

    public function trainer(): static
    {
        return $this->state(fn (array $attributes) => ['role' => Role::Trainer]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    /**
     * Usuario que aún no activó el 2FA por correo (RF-04): el panel lo obliga a configurarlo.
     */
    public function withoutTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => ['has_email_authentication' => false]);
    }
}
