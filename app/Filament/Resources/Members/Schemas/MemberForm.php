<?php

declare(strict_types=1);

namespace App\Filament\Resources\Members\Schemas;

use App\Enums\DocumentType;
use App\Enums\Role;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Formulario de socios (RF-09, RF-10, RN-11, RN-13).
 *
 * Solo configura la interfaz: la autorización está en MemberPolicy y la
 * normalización del teléfono a solo dígitos vive en el modelo Member (RN-11).
 * La unicidad del documento es la regla de RN-11: la regla `unique` de Laravel
 * consulta la tabla directamente, sin el scope de SoftDeletes, así que
 * también bloquea documentos de socios eliminados lógicamente (se restauran,
 * no se vuelven a crear). El índice único de la base de datos es la garantía
 * final.
 */
class MemberForm
{
    /** Teléfono: dígitos y separadores comunes, con al menos un dígito. */
    private const PHONE_REGEX = '/^[0-9+\-\s()]*\d[0-9+\-\s()]*$/';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('document_type')
                    ->label('Tipo de documento')
                    ->options(DocumentType::class)
                    ->default(DocumentType::CC->value)
                    ->required()
                    ->native(false),
                TextInput::make('document_number')
                    ->label('Número de documento')
                    ->required()
                    ->maxLength(20)
                    ->regex('/^[A-Za-z0-9]+$/')
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                            ->where('document_type', $get('document_type')),
                    )
                    ->validationMessages([
                        'regex' => 'El número de documento solo admite letras y números.',
                        'unique' => 'Ya existe un socio con este tipo y número de documento. Si fue eliminado, restáurelo en lugar de crearlo.',
                    ]),
                TextInput::make('first_name')
                    ->label('Nombres')
                    ->required()
                    ->maxLength(100),
                TextInput::make('last_name')
                    ->label('Apellidos')
                    ->required()
                    ->maxLength(100),
                TextInput::make('phone')
                    ->label('Teléfono')
                    ->required()
                    ->maxLength(20)
                    ->regex(self::PHONE_REGEX)
                    ->validationMessages([
                        'regex' => 'El teléfono solo admite números y los símbolos + - ( ).',
                    ]),
                TextInput::make('email')
                    ->label('Correo electrónico')
                    ->email()
                    ->maxLength(255),
                DatePicker::make('birth_date')
                    ->label('Fecha de nacimiento')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->maxDate(today()),
                Select::make('trainer_id')
                    ->label('Entrenador asignado')
                    ->relationship(
                        name: 'trainer',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query
                            ->where('is_active', true)
                            ->where('role', Role::Trainer),
                    )
                    ->searchable()
                    ->preload()
                    // RN-13: debe ser un usuario activo con rol Entrenador.
                    // Se valida también en el servidor, no solo en las opciones.
                    ->rule(
                        Rule::exists('users', 'id')
                            ->where('is_active', true)
                            ->where('role', Role::Trainer->value),
                    ),
                TextInput::make('emergency_contact_name')
                    ->label('Contacto de emergencia')
                    ->maxLength(120),
                TextInput::make('emergency_contact_phone')
                    ->label('Teléfono del contacto de emergencia')
                    ->maxLength(20)
                    ->regex(self::PHONE_REGEX)
                    ->validationMessages([
                        'regex' => 'El teléfono solo admite números y los símbolos + - ( ).',
                    ]),
                Textarea::make('notes')
                    ->label('Notas')
                    ->helperText('Observaciones generales. No registre datos clínicos.')
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
