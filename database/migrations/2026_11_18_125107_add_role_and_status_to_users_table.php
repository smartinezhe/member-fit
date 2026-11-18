<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Añade los campos de MemberFit a users (sección 9 del documento):
     * rol, teléfono, estado activo y bandera del 2FA por correo.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Sin valor por defecto a propósito: el rol siempre se asigna de forma explícita.
            $table->string('role', 20)->after('password')->index();
            $table->string('phone', 20)->nullable()->after('role');
            $table->boolean('is_active')->default(true)->after('phone');
            $table->boolean('has_email_authentication')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'phone', 'is_active', 'has_email_authentication']);
        });
    }
};
