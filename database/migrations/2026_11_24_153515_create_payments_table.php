<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla de pagos (sección 9 del documento).
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Un solo pago por membresía, sin abonos (RN-04): el índice único
            // en membership_id lo garantiza en la base de datos.
            $table->foreignId('membership_id')
                ->unique()
                ->constrained('memberships')
                ->restrictOnDelete();

            // Valor total pagado en COP (RN-03).
            $table->decimal('amount', 12, 2);

            // Enum PaymentMethod: cash, transfer, card.
            $table->string('method', 20);

            // Fecha y hora del registro; los ingresos se agrupan por este
            // campo en America/Bogota (RN-09).
            $table->dateTime('paid_at')->index();

            $table->timestamps();
        });

        // Regla de la sección 9: amount > 0 (RN-03). MySQL 8.0.16+ la aplica.
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
