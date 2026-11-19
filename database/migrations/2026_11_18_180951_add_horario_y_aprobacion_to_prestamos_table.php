<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada solicitud es de un solo espacio o equipo, para una fecha y un bloque de su horario.
     * Se guarda la hora del bloque (no solo una referencia) para que el historial no cambie
     * si después se editan los horarios.
     */
    public function up(): void
    {
        Schema::table('prestamos', function (Blueprint $table) {
            $table->date('fecha')->nullable()->after('estado');
            $table->time('hora_inicio')->nullable()->after('fecha');
            $table->time('hora_fin')->nullable()->after('hora_inicio');
            // automatica: quedó aprobada sola porque estaba libre; funcionario: la aprobó una persona.
            $table->enum('aprobacion', ['automatica', 'funcionario'])->nullable()->after('hora_fin');
            $table->text('motivo_rechazo')->nullable()->after('observaciones');
            $table->dateTime('cancelado_en')->nullable()->after('fecha_devolucion');

            $table->index(['equipo_id', 'fecha', 'estado']);
            $table->index(['solicitante_id', 'estado']);
        });

        // Nuevo estado: cancelado (por el mismo solicitante).
        Schema::table('prestamos', function (Blueprint $table) {
            $table->enum('estado', ['pendiente', 'aprobado', 'rechazado', 'cancelado', 'devuelto'])->default('pendiente')->change();
        });

        // Préstamos anteriores a este cambio: su fecha de uso es la fecha esperada de devolución.
        DB::table('prestamos')->whereNull('fecha')->update(['fecha' => DB::raw('fecha_esperada_devolucion')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('prestamos')->where('estado', 'cancelado')->update(['estado' => 'rechazado']);

        Schema::table('prestamos', function (Blueprint $table) {
            $table->enum('estado', ['pendiente', 'aprobado', 'rechazado', 'devuelto'])->default('pendiente')->change();
        });

        Schema::table('prestamos', function (Blueprint $table) {
            $table->dropIndex(['equipo_id', 'fecha', 'estado']);
            $table->dropIndex(['solicitante_id', 'estado']);
            $table->dropColumn(['fecha', 'hora_inicio', 'hora_fin', 'aprobacion', 'motivo_rechazo', 'cancelado_en']);
        });
    }
};
