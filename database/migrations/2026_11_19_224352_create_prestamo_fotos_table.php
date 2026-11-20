<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fotos opcionales de una entrega o de una revisión de la devolución. Se guardan en el disco
 * privado: solo se ven con la sesión iniciada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestamo_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestamo_id')->constrained('prestamos')->cascadeOnDelete();
            $table->foreignId('entrega_id')->nullable()->constrained('entregas')->cascadeOnDelete();
            $table->foreignId('devolucion_id')->nullable()->constrained('devoluciones')->cascadeOnDelete();
            $table->string('ruta');
            $table->string('nombre_original');
            $table->string('tipo_mime', 100);
            $table->unsignedInteger('tamano');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamo_fotos');
    }
};
