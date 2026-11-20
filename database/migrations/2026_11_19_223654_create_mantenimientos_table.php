<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mantenimientos programados de un espacio o equipo: desde una fecha, hasta otra (o sin fin),
 * con su motivo. En esas fechas nadie lo puede pedir. Un rechazo absoluto de una devolución
 * crea uno sin fecha de fin (prestamo_id dice cuál préstamo lo originó).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mantenimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_id')->constrained('equipos')->cascadeOnDelete();
            $table->date('desde');
            $table->date('hasta')->nullable();
            $table->string('motivo', 500);
            $table->foreignId('creado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('prestamo_id')->nullable()->constrained('prestamos')->nullOnDelete();
            $table->timestamps();

            $table->index(['equipo_id', 'desde', 'hasta']);
        });

        // Los equipos que hoy están en mantenimiento (elegido a mano) pasan a un mantenimiento sin fecha de fin.
        $ahora = now();
        DB::table('mantenimientos')->insert(DB::table('equipos')->where('estado', 'mantenimiento')->pluck('id')
            ->map(fn ($id) => ['equipo_id' => $id, 'desde' => $ahora->toDateString(), 'hasta' => null, 'motivo' => 'En mantenimiento.', 'created_at' => $ahora, 'updated_at' => $ahora])
            ->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('mantenimientos');
    }
};
