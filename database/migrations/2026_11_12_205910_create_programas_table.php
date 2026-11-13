<?php

use Database\Seeders\ProgramaSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Programas académicos de las UTS (oferta académica oficial).
     * Se usan para saber a qué carrera pertenece cada estudiante o docente, y luego en los reportes.
     */
    public function up(): void
    {
        Schema::create('programas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->enum('nivel', ['tecnologia', 'universitario']);
            $table->enum('modalidad', ['presencial', 'virtual']);
            $table->string('sede', 40);
            // Código SNIES: el número con el que el Ministerio de Educación identifica cada programa. No se repite.
            $table->unsignedInteger('snies')->unique();
            $table->timestamps();

            $table->index(['sede', 'nombre']);
        });

        // La lista de programas es parte del sistema (sin ella no se puede crear un estudiante),
        // así que se carga al migrar. El seeder también se puede volver a correr solo, sin duplicar.
        (new ProgramaSeeder)->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('programas');
    }
};
