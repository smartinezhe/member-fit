<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Role;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * Formulario de usuarios (RF-06). Solo configura la interfaz: la autorización
 * está en UserPolicy y el cifrado de la contraseña en el cast `hashed` del modelo.
 */
class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Correo electrónico')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('phone')
                    ->label('Teléfono')
                    ->tel()
                    ->maxLength(20),
                Select::make('role')
                    ->label('Rol')
                    ->options(Role::class)
                    ->required()
                    // RN-14: el último administrador activo no puede cambiar de rol.
                    ->disabled(fn(?User $record): bool => $record?->isLastActiveAdmin() ?? false),
                TextInput::make('password')
                    ->label('Contraseña')
                    ->password()
                    ->minLength(8)
                    // Obligatoria al crear; al editar solo se cambia si se escribe una nueva.
                    ->required(fn(string $operation): bool => $operation === 'create')
                    ->dehydrated(fn(?string $state): bool => filled($state)),
                Toggle::make('is_active')
                    ->label('Activo')
                    ->default(true)
                    // RN-14: el administrador no puede desactivarse a sí mismo.
                    ->disabled(fn(?User $record): bool => $record?->is(Auth::user()) ?? false),
            ])
            ->columns(2);
    }
}
