<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Por defecto crea la asistencia de un socio nuevo, registrada hoy y ahora.
     * Las pruebas de borde sobrescriben attended_on y checked_in_at.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'checked_in_by' => User::factory(),
            'attended_on' => today()->toDateString(),
            'checked_in_at' => now(),
        ];
    }
}
