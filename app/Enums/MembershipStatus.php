<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonInterface;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Estado de membresía de un socio (RN-05).
 *
 * NO se guarda en la base de datos: se calcula siempre a partir de la fecha
 * de vencimiento de la membresía no anulada más reciente. Esta clase es el
 * único lugar donde viven las reglas en PHP; los scopes de Member
 * (inStatus) las replican en SQL para poder filtrar tablas, y una prueba
 * verifica que ambas versiones coincidan.
 */
enum MembershipStatus: string implements HasColor, HasIcon, HasLabel
{
    case Valid = 'valid';
    case ExpiringSoon = 'expiring_soon';
    case Expired = 'expired';
    case NoMembership = 'no_membership';

    /**
     * Calcula el estado a partir del vencimiento más reciente (RN-05).
     *
     * @param  CarbonInterface|null  $endsOn  ends_on de la membresía no anulada más reciente; null si no tiene ninguna.
     * @param  int  $alertDays  Umbral N de RN-07 (config memberfit.expiry_alert_days).
     */
    public static function fromEndsOn(?CarbonInterface $endsOn, int $alertDays): self
    {
        if ($endsOn === null) {
            return self::NoMembership;
        }

        $today = today();
        $endsOn = $endsOn->copy()->startOfDay();

        // Vencida: ends_on < hoy.
        if ($endsOn->lt($today)) {
            return self::Expired;
        }

        // Por vencer: hoy <= ends_on <= hoy + N.
        if ($endsOn->lte($today->copy()->addDays($alertDays))) {
            return self::ExpiringSoon;
        }

        // Vigente: ends_on > hoy + N.
        return self::Valid;
    }

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Valid => 'Vigente',
            self::ExpiringSoon => 'Por vencer',
            self::Expired => 'Vencida',
            self::NoMembership => 'Sin membresía',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Valid => 'success',
            self::ExpiringSoon => 'warning',
            self::Expired => 'danger',
            self::NoMembership => 'gray',
        };
    }

    /**
     * El estado se distingue por icono y texto, no solo por color (RNF-05).
     */
    public function getIcon(): ?string
    {
        return match ($this) {
            self::Valid => 'heroicon-m-check-circle',
            self::ExpiringSoon => 'heroicon-m-clock',
            self::Expired => 'heroicon-m-x-circle',
            self::NoMembership => 'heroicon-m-minus-circle',
        };
    }
}
