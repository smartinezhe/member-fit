<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Roles fijos del sistema (DP-01, RF-07).
 *
 * El valor de cada caso es lo que se guarda en la columna users.role.
 * Las Policies y los scopes se basan en este enum; no hay editor de permisos.
 * Implementa HasLabel para que Filament muestre el nombre en español
 * en selectores, filtros y columnas.
 */
enum Role: string implements HasLabel
{
    case Admin = 'admin';
    case Receptionist = 'receptionist';
    case Trainer = 'trainer';

    /** Nombre del rol para mostrar en la interfaz. */
    public function getLabel(): ?string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Receptionist => 'Recepcionista',
            self::Trainer => 'Entrenador',
        };
    }
}
