<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Tipos de documento de identidad aceptados para los socios (sección 9).
 *
 * El valor de cada caso es la sigla de 2 letras que se guarda en
 * members.document_type. Implementa HasLabel para que Filament muestre
 * el nombre completo en español en selectores, filtros y columnas (RNF-08).
 */
enum DocumentType: string implements HasLabel
{
    case CC = 'CC';
    case TI = 'TI';
    case CE = 'CE';
    case PA = 'PA';

    /** Nombre completo del tipo de documento para mostrar en la interfaz. */
    public function getLabel(): ?string
    {
        return match ($this) {
            self::CC => 'Cédula de ciudadanía',
            self::TI => 'Tarjeta de identidad',
            self::CE => 'Cédula de extranjería',
            self::PA => 'Pasaporte',
        };
    }
}
