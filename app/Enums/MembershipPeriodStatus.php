<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Estado de UNA membresía en el historial del socio (RF-12).
 *
 * No es el estado del socio (ver MembershipStatus, RN-05): este describe el
 * periodo de una membresía concreta. Se calcula con RN-01 y RN-08 y no se
 * almacena. «Próxima» existe porque las renovaciones anticipadas (RN-02)
 * crean membresías que empiezan en el futuro.
 */
enum MembershipPeriodStatus: string implements HasColor, HasIcon, HasLabel
{
    case Active = 'active';
    case Upcoming = 'upcoming';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Active => 'Vigente',
            self::Upcoming => 'Próxima',
            self::Expired => 'Vencida',
            self::Cancelled => 'Anulada',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Active => 'success',
            self::Upcoming => 'info',
            self::Expired => 'gray',
            self::Cancelled => 'danger',
        };
    }

    /** Se distingue por icono y texto, no solo por color (RNF-05). */
    public function getIcon(): ?string
    {
        return match ($this) {
            self::Active => 'heroicon-m-check-circle',
            self::Upcoming => 'heroicon-m-calendar-days',
            self::Expired => 'heroicon-m-clock',
            self::Cancelled => 'heroicon-m-x-circle',
        };
    }
}
