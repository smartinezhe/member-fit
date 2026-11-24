<?php

declare(strict_types=1);

namespace App\Filament\Resources\Members\Tables;

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Member;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Tabla de socios (RF-11).
 *
 * Solo configura la interfaz. El estado de membresía (RN-05) no se almacena:
 * se precarga con el scope withLatestEndDate() y se filtra con inStatus(), ambos
 * definidos en Member, para no hacer una consulta por fila (N+1).
 * Las acciones de eliminar y restaurar se muestran según MemberPolicy
 * (solo el administrador, RN-11). No hay acciones masivas.
 */
class MembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                $query->with('trainer')->withLatestEndDate();

                // El entrenador solo ve sus socios asignados (RN-13, RF-23).
                // Sin un usuario válido no se muestra ningún registro.
                $user = Auth::user();

                return $user instanceof User
                    ? $query->visibleTo($user)
                    : $query->whereRaw('1 = 0');
            })
            ->columns([
                TextColumn::make('full_name')
                    ->label('Socio')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name']),
                TextColumn::make('document_number')
                    ->label('Documento')
                    ->formatStateUsing(fn (string $state, Member $record): string => "{$record->document_type->value} {$state}")
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable(),
                // Estado calculado (RN-05): etiqueta, color e icono vienen del enum.
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
                TextColumn::make('trainer.name')
                    ->label('Entrenador')
                    ->placeholder('Sin asignar'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado de membresía')
                    ->options(MembershipStatus::class)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->inStatus(MembershipStatus::from($data['value']))
                        : $query),
                // El entrenador solo ve sus socios, así que el filtro no le sirve.
                SelectFilter::make('trainer_id')
                    ->label('Entrenador')
                    ->relationship(
                        name: 'trainer',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->where('role', Role::Trainer),
                    )
                    ->visible(fn (): bool => self::userHasRole(Role::Admin, Role::Receptionist)),
                // Eliminados lógicos: solo el administrador los consulta y restaura (RN-11).
                TrashedFilter::make()
                    ->visible(fn (): bool => self::userHasRole(Role::Admin)),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->defaultSort('first_name')
            ->defaultPaginationPageOption(25);
    }

    /**
     * Indica si el usuario autenticado tiene alguno de los roles dados.
     *
     * Se comprueba con instanceof para que el análisis estático sepa que
     * es un App\Models\User (Auth::user() devuelve Authenticatable) y para
     * tratar con seguridad el caso sin sesión.
     */
    private static function userHasRole(Role ...$roles): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->hasRole(...$roles);
    }
}
