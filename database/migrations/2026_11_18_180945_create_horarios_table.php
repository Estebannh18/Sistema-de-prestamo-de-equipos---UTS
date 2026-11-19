<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bloques de horas en que se presta cada espacio o equipo, por día de la semana.
     * Solo de lunes (1) a sábado (6): los domingos la universidad no labora.
     */
    public function up(): void
    {
        Schema::create('horarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_id')->constrained('equipos')->cascadeOnDelete();
            $table->unsignedTinyInteger('dia_semana'); // 1 = lunes … 6 = sábado
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->timestamps();

            $table->index(['equipo_id', 'dia_semana']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('horarios');
    }
};
