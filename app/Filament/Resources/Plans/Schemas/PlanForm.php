<?php

declare(strict_types=1);

namespace App\Filament\Resources\Plans\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

/**
 * Formulario de planes de membresía (RF-08, RN-12).
 *
 * Solo configura la interfaz: la autorización está en PlanPolicy. Las reglas de
 * RN-12 (nombre único, duración >= 1 día, precio > 0) se validan aquí y el
 * «activar/desactivar» reemplaza a la eliminación, que nadie tiene.
 */
class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(120)
                    ->unique(ignoreRecord: true),
                TextInput::make('description')
                    ->label('Descripción')
                    ->maxLength(255),
                TextInput::make('duration_days')
                    ->label('Duración (días)')
                    ->required()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(65535),
                TextInput::make('price')
                    ->label('Precio')
                    ->required()
                    ->numeric()
                    ->minValue(0.01)
                    ->maxValue(9999999999.99)
                    ->prefix('$')
                    ->suffix('COP'),
                Toggle::make('is_active')
                    ->label('Activo')
                    ->helperText('Un plan inactivo no se ofrece en nuevas membresías; las ya registradas lo conservan.')
                    ->default(true),
            ])
            ->columns(2);
    }
}
