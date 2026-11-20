<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entrega de una solicitud aprobada: siempre al solicitante, en persona. Todo se escribe en cada
 * entrega (nada viene prellenado): estado, complementos, condiciones para devolverlo y observaciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entregas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestamo_id')->unique()->constrained('prestamos')->cascadeOnDelete();
            $table->foreignId('funcionario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('entregado_en');
            $table->enum('estado', ['bueno', 'con_observaciones']);
            $table->text('complementos');
            $table->text('condiciones')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entregas');
    }
};
