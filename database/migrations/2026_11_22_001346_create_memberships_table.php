<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla de membresías (sección 9 del documento).
     */
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();

            // Usuario que registró la membresía.
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            // Vigencia inclusiva (RN-01, RN-02).
            $table->date('starts_on');
            $table->date('ends_on');

            // Anulación (RN-08): el motivo es obligatorio solo si hay fecha.
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            $table->timestamps();

            // Estado del socio y alertas de vencimiento (RN-05, RN-07).
            $table->index(['member_id', 'ends_on']);
            $table->index('ends_on');
        });

        // Regla de la sección 9: fin >= inicio. MySQL 8.0.16+ la aplica.
        DB::statement('ALTER TABLE memberships ADD CONSTRAINT memberships_dates_check CHECK (ends_on >= starts_on)');
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
