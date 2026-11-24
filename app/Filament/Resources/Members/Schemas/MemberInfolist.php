<?php

declare(strict_types=1);

namespace App\Filament\Resources\Members\Schemas;

use App\Models\Member;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

/**
 * Ficha del socio (RF-12): datos personales y estado de membresía.
 *
 * El historial de membresías y las últimas asistencias se añaden en los
 * Días 4 y 6, cuando existan sus gestores de relaciones. Solo configura la
 * interfaz; el estado se calcula en el modelo (RN-05).
 */
class MemberInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('full_name')
                    ->label('Socio'),
                // Estado calculado (RN-05): etiqueta, color e icono vienen del enum.
                TextEntry::make('status')
                    ->label('Estado de membresía')
                    ->badge(),
                TextEntry::make('expires_on')
                    ->label('Vence')
                    ->state(fn (Member $record): ?string => $record->memberships()
                        ->notCancelled()
                        ->max('ends_on'))
                    ->date('d/m/Y')
                    ->placeholder('Sin membresía'),
                TextEntry::make('trainer.name')
                    ->label('Entrenador')
                    ->placeholder('Sin asignar'),
                TextEntry::make('document_type')
                    ->label('Tipo de documento'),
                TextEntry::make('document_number')
                    ->label('Número de documento'),
                TextEntry::make('phone')
                    ->label('Teléfono'),
                TextEntry::make('email')
                    ->label('Correo electrónico')
                    ->placeholder('—'),
                TextEntry::make('birth_date')
                    ->label('Fecha de nacimiento')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                TextEntry::make('emergency_contact_name')
                    ->label('Contacto de emergencia')
                    ->placeholder('—'),
                TextEntry::make('emergency_contact_phone')
                    ->label('Teléfono del contacto de emergencia')
                    ->placeholder('—'),
                TextEntry::make('notes')
                    ->label('Notas')
                    ->placeholder('—')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
