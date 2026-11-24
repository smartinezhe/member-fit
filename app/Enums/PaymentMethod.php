<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Métodos de pago aceptados (RN-04, DP-03). La lista es fija.
 *
 * El valor de cada caso es lo que se guarda en payments.method.
 */
enum PaymentMethod: string implements HasLabel
{
    case Cash = 'cash';
    case Transfer = 'transfer';
    case Card = 'card';

    /** Nombre del método para mostrar en la interfaz (RNF-08). */
    public function getLabel(): ?string
    {
        return match ($this) {
            self::Cash => 'Efectivo',
            self::Transfer => 'Transferencia',
            self::Card => 'Tarjeta',
        };
    }
}
