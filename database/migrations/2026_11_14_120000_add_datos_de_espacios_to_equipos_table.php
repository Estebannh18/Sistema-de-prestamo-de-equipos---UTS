<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los espacios (salones, laboratorios) se describen distinto a los equipos:
     *  - tienen ubicación y capacidad en vez de marca y modelo;
     *  - pueden tener carreras recomendadas (orientativas: no impiden que otro docente los pida);
     *  - solo los piden docentes.
     */
    public function up(): void
    {
        Schema::table('equipos', function (Blueprint $table) {
            $table->string('ubicacion', 100)->nullable()->after('modelo');
            $table->unsignedSmallInteger('capacidad')->nullable()->after('ubicacion');
        });

        Schema::create('equipo_programa', function (Blueprint $table) {
            $table->foreignId('equipo_id')->constrained('equipos')->cascadeOnDelete();
            $table->foreignId('programa_id')->constrained('programas')->cascadeOnDelete();
            $table->primary(['equipo_id', 'programa_id']);
        });

        // Los espacios que ya existían pasan a ser solo para docentes.
        DB::table('equipos')
            ->whereIn('categoria_id', DB::table('categorias')->where('tipo', 'espacio')->select('id'))
            ->update(['solicitable_por' => 'docente']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipo_programa');

        Schema::table('equipos', function (Blueprint $table) {
            $table->dropColumn(['ubicacion', 'capacidad']);
        });
    }
};
