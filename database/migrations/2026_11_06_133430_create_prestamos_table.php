<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('prestamos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_id')->constrained('equipos')->restrictOnDelete();
            $table->foreignId('solicitante_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('funcionario_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->enum('estado', ['pendiente', 'aprobado', 'rechazado', 'devuelto'])->default('pendiente');
            $table->date('fecha_esperada_devolucion');
            $table->dateTime('fecha_respuesta')->nullable();
            $table->dateTime('fecha_devolucion')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prestamos');
    }
};
