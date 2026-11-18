<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Roles fijos del sistema (DP-01, RF-07).
 *
 * El valor de cada caso es lo que se guarda en la columna users.role.
 * Las Policies y los scopes se basan en este enum; no hay editor de permisos.
 */
enum Role: string
{
    case Admin = 'admin';
    case Receptionist = 'receptionist';
    case Trainer = 'trainer';

    /** Nombre del rol para mostrar en la interfaz (es-CO). */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Receptionist => 'Recepcionista',
            self::Trainer => 'Entrenador',
        };
    }
}
