<?php

use App\Models\Configuracion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Valores del módulo Configuración (administrador). Se cargan con sus valores por defecto,
     * para que el sistema funcione desde el primer momento.
     */
    public function up(): void
    {
        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 60)->unique();
            $table->unsignedInteger('valor');
            $table->timestamps();
        });

        $ahora = now();
        DB::table('configuraciones')->insert(collect(Configuracion::VALORES)->map(fn ($definicion, $clave) => [
            'clave' => $clave,
            'valor' => $definicion[3],
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ])->values()->all());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
    }
};
