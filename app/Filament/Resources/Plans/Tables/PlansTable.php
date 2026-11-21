<?php

declare(strict_types=1);

namespace App\Filament\Resources\Plans\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Tabla de planes (RF-08). Sin acciones de eliminar: un plan nunca se elimina,
 * solo se desactiva (RN-12).
 */
class PlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('duration_days')
                    ->label('Duración (días)')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Precio')
                    ->money('COP')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Activo'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('name')
            ->defaultPaginationPageOption(25);
    }
}
