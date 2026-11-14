<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Antes los salones se reconocían por el nombre de su categoría («Salones»).
     * Ahora cada categoría dice si agrupa equipos o espacios, y qué ícono la representa.
     */
    public function up(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->enum('tipo', ['equipo', 'espacio'])->default('equipo')->after('descripcion');
            $table->string('icono', 30)->nullable()->after('tipo');
        });

        // La categoría que ya existía para los salones pasa a ser de espacios.
        DB::table('categorias')->where('nombre', 'Salones')->update(['tipo' => 'espacio', 'icono' => 'salon']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'icono']);
        });
    }
};
