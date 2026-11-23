<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentType;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

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

    /**
     * Estado de membresía calculado (RN-05).
     *
     * En listados, usar antes el scope withLatestEndDate() para que el valor
     * venga precargado y no se haga una consulta por socio. Si no está
     * precargado (por ejemplo en la ficha de un solo socio), se consulta aquí.
     *
     * @return Attribute<MembershipStatus, never>
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: function (): MembershipStatus {
                $latest = array_key_exists('latest_ends_on', $this->attributes)
                    ? $this->attributes['latest_ends_on']
                    : $this->memberships()->notCancelled()->max('ends_on');

                return MembershipStatus::fromEndsOn(
                    $latest === null ? null : Carbon::parse($latest),
                    (int) config('memberfit.expiry_alert_days'),
                );
            },
        );
    }

    /**
     * Precarga latest_ends_on: vencimiento de la membresía no anulada más
     * reciente. Evita consultas N+1 al mostrar el estado en tablas (RN-05).
     *
     * @param  Builder<Member>  $query
     */
    #[Scope]
    protected function withLatestEndDate(Builder $query): void
    {
        $query->withMax(
            ['memberships as latest_ends_on' => fn (Builder $q) => $q->notCancelled()],
            'ends_on',
        );
    }

    /**
     * Filtra socios por estado de membresía en SQL (RF-11, RN-05).
     *
     * Equivale a mirar la membresía más reciente porque las renovaciones son
     * continuas (RN-02). Replica MembershipStatus::fromEndsOn().
     *
     * @param  Builder<Member>  $query
     */
    #[Scope]
    protected function inStatus(Builder $query, MembershipStatus $status): void
    {
        $today = today()->toDateString();
        $limit = today()->addDays((int) config('memberfit.expiry_alert_days'))->toDateString();

        match ($status) {
            // Alguna membresía vence después de hoy + N.
            MembershipStatus::Valid => $query->whereHas(
                'memberships',
                fn (Builder $q) => $q->notCancelled()->where('ends_on', '>', $limit),
            ),
            // Alguna vence desde hoy, pero ninguna después de hoy + N.
            MembershipStatus::ExpiringSoon => $query
                ->whereHas('memberships', fn (Builder $q) => $q->notCancelled()->where('ends_on', '>=', $today))
                ->whereDoesntHave('memberships', fn (Builder $q) => $q->notCancelled()->where('ends_on', '>', $limit)),
            // Tiene membresías, pero ninguna vence desde hoy.
            MembershipStatus::Expired => $query
                ->whereHas('memberships', fn (Builder $q) => $q->notCancelled())
                ->whereDoesntHave('memberships', fn (Builder $q) => $q->notCancelled()->where('ends_on', '>=', $today)),
            // Ninguna membresía no anulada.
            MembershipStatus::NoMembership => $query->whereDoesntHave(
                'memberships',
                fn (Builder $q) => $q->notCancelled(),
            ),
        };
    }

    /**
     * Acota la consulta a los socios que el usuario puede ver (RN-13, RF-23).
     *
     * Administrador y recepcionista ven todos; el entrenador solo los que
     * tiene asignados. Es la contraparte en listas de MemberPolicy::view().
     *
     * @param  Builder<Member>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->hasRole(Role::Trainer)) {
            $query->where('trainer_id', $user->id);
        }
    }
}
