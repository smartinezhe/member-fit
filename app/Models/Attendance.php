<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asistencia de un socio: una por socio y día (RN-06).
 *
 * Solo se registra un ingreso permitido; los intentos denegados no se guardan
 * (RN-06, DP-05). La unicidad por socio y día la garantiza el índice único de
 * la base de datos. La crea la Action RegisterCheckIn (Día 6, Paso 2). Un
 * administrador puede eliminar una asistencia registrada por error (RF-19):
 * es una eliminación real, la tabla no tiene eliminación lógica.
 */
#[Fillable(['member_id', 'checked_in_by', 'attended_on', 'checked_in_at'])]
class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'attended_on' => 'date',
            'checked_in_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Usuario que registró el ingreso.
     *
     * @return BelongsTo<User, $this>
     */
    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }
}
