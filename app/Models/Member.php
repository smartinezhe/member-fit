<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentType;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Socio del gimnasio (RF-09).
 *
 * Reglas que viven aquí:
 * - El documento (tipo + número) es único, incluso frente a socios eliminados
 *   lógicamente; se garantiza con el índice único de la base de datos (RN-11).
 * - El teléfono se guarda solo con dígitos, sin importar por dónde entre el
 *   dato (formulario, seeder, importación) (RN-11).
 * - El estado de membresía NO se almacena: se calcula con scopes (RN-05).
 *   Se añaden junto con la relación memberships() en el Paso 3.
 * - Un socio con membresías o asistencias no se elimina físicamente (RN-11);
 *   la eliminación es siempre lógica (SoftDeletes).
 */
#[Fillable([
    'document_type',
    'document_number',
    'first_name',
    'last_name',
    'phone',
    'email',
    'birth_date',
    'emergency_contact_name',
    'emergency_contact_phone',
    'notes',
    'trainer_id',
])]
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'birth_date' => 'date',
        ];
    }

    /**
     * Teléfono normalizado a solo dígitos al asignarlo (RN-11).
     *
     * @return Attribute<string, string>
     */
    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => preg_replace('/\D+/', '', $value) ?? '',
        );
    }

    /**
     * Nombre completo para tablas, ficha y pantalla de check-in.
     *
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => "{$this->first_name} {$this->last_name}",
        );
    }

    /**
     * Entrenador asignado, opcional (RN-13). La validación de que sea un
     * usuario activo con rol Entrenador va en el formulario del recurso.
     *
     * @return BelongsTo<User, $this>
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    /**
     * Historial de membresías del socio (RF-12).
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }
}
