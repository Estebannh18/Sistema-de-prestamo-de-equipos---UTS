<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tipo de documento y estado de la cuenta.
     * Los usuarios que ya existen quedan con cédula de ciudadanía y activos.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('tipo_documento', ['CC', 'TI', 'CE', 'PA'])->default('CC')->after('rol');
            $table->boolean('activo')->default(true)->after('programa_dependencia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['tipo_documento', 'activo']);
        });
    }
};
