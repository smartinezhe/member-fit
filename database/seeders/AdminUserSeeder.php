<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Crea (o actualiza) el administrador inicial del sistema.
 *
 * No existe registro público (RF-05), por lo que esta es la primera cuenta con la
 * que se entra al panel. Es idempotente: se puede ejecutar varias veces sin duplicar.
 * Las credenciales son ficticias, solo para demostración local; se documentan en el README.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@memberfit.test'],
            [
                'name' => 'Administrador MemberFit',
                'password' => 'password',
                'role' => Role::Admin,
                'is_active' => true,
            ],
        );
    }
}
