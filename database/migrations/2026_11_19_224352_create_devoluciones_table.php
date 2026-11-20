<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada revisión de la devolución de una solicitud entregada. Puede haber varias: por ejemplo,
 * «por corregir» y después «aceptada». Todo se escribe a mano en cada revisión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devoluciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestamo_id')->constrained('prestamos')->cascadeOnDelete();
            $table->foreignId('funcionario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('recibido_en');
            $table->unsignedInteger('minutos_retraso')->default(0);
            $table->enum('estado', ['bueno', 'sucio', 'danado']);
            $table->text('complementos');
            $table->boolean('complementos_completos');
            $table->text('faltantes')->nullable();
            $table->boolean('condiciones_cumplidas')->nullable();
            $table->text('observaciones')->nullable();
            $table->enum('resultado', ['aceptada', 'por_corregir', 'rechazada']);
            $table->text('motivo')->nullable();
            $table->string('plazo', 100)->nullable();
            $table->text('pendiente')->nullable();
            $table->dateTime('pendiente_resuelto_en')->nullable();
            $table->foreignId('pendiente_resuelto_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['prestamo_id', 'recibido_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devoluciones');
    }
};
