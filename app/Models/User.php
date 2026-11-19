<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use DomainException;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Usuario del panel (administrador, recepcionista o entrenador).
 *
 * No hay registro público (RF-05): solo el administrador crea cuentas desde el
 * recurso de usuarios. `role`, `phone` e `is_active` son asignables en masa porque
 * únicamente ese recurso (restringido al administrador por su Policy) los escribe.
 */
#[Fillable(['name', 'email', 'password', 'phone', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'has_email_authentication' => 'boolean',
        ];
    }

    /**
     * Decide si el usuario puede entrar al panel (RF-01, RN-14).
     *
     * Un usuario inactivo no accede al panel, sin importar su rol ni el entorno.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    /**
     * Indica si el usuario tiene alguno de los roles dados.
     *
     * Punto único de comparación de roles para las Policies (RF-07).
     */
    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Es el único administrador activo del sistema (RN-14).
     *
     * Se usa para impedir que el sistema se quede sin administrador activo.
     */
    public function isLastActiveAdmin(): bool
    {
        if (! $this->hasRole(Role::Admin) || ! $this->is_active) {
            return false;
        }

        return ! static::query()
            ->whereKeyNot($this->getKey())
            ->where('role', Role::Admin->value)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Guardián de RN-14: no permite que una actualización deje al sistema sin
     * administrador activo (por desactivarlo o por quitarle el rol).
     *
     * La interfaz ya deshabilita esos campos; esta es la defensa de fondo.
     */
    protected static function booted(): void
    {
        static::updating(function (User $user): void {
            if (! $user->isDirty(['role', 'is_active'])) {
                return;
            }

            // El usuario seguirá siendo administrador activo: el cambio es válido.
            if ($user->hasRole(Role::Admin) && $user->is_active) {
                return;
            }

            // Se evalúa el estado guardado en la base de datos, no el modificado en memoria.
            if ($user->fresh()?->isLastActiveAdmin()) {
                throw new DomainException('Debe quedar al menos un administrador activo.');
            }
        });
    }
}
