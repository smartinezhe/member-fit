<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla de socios (sección 9 del documento).
     */
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();

            // Documento de identidad (enum DocumentType). La unicidad abarca
            // también a los socios eliminados lógicamente (RN-11).
            $table->string('document_type', 2);
            $table->string('document_number', 20);

            $table->string('first_name', 100);
            $table->string('last_name', 100);

            // Solo dígitos (RN-11); indexado para la búsqueda (RF-11).
            $table->string('phone', 20)->index();

            $table->string('email')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('emergency_contact_name', 120)->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();

            // Observaciones generales; no se registran datos clínicos.
            $table->text('notes')->nullable();

            // Entrenador asignado, opcional (RN-13). Restrict en eliminación.
            $table->foreignId('trainer_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->unique(['document_type', 'document_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
