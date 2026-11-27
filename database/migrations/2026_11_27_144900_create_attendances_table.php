<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla de asistencias (sección 9 del documento).
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();

            // Usuario que registró el ingreso.
            $table->foreignId('checked_in_by')->constrained('users')->restrictOnDelete();

            // Día de la asistencia (zona America/Bogota) y hora del ingreso.
            $table->date('attended_on')->index();
            $table->dateTime('checked_in_at');

            $table->timestamps();

            // Una sola asistencia por socio y día (RN-06): la garantía final está
            // en la base de datos, incluso con dos peticiones simultáneas (RNF-03).
            $table->unique(['member_id', 'attended_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
