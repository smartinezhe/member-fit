<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Plan de membresía: duración en días y precio en COP (RF-08).
 *
 * Un plan nunca se elimina: se desactiva (RN-12). Un plan inactivo no se
 * ofrece en nuevas membresías, pero las ya registradas conservan su plan.
 * Los cambios de precio no afectan pagos ya registrados (RN-03), porque
 * cada pago guarda su propio valor.
 */
#[Fillable(['name', 'description', 'duration_days', 'price', 'is_active'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Planes disponibles para nuevas membresías (RN-12).
     *
     * @param  Builder<Plan>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
