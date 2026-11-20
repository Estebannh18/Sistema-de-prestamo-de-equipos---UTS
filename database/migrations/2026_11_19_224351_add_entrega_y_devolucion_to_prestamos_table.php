<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estados nuevos de una solicitud para las entregas y devoluciones:
 * entregado, no recogido (el bloque terminó sin entrega), no entregado (no se pudo entregar, con motivo),
 * por corregir (la devolución no se recibió todavía) y devolución rechazada (llegó dañado).
 */
return new class extends Migration
{
    private const ANTES = ['pendiente', 'aprobado', 'rechazado', 'cancelado', 'devuelto'];

    private const AHORA = ['pendiente', 'aprobado', 'rechazado', 'cancelado', 'entregado', 'no_recogido', 'no_entregado', 'por_corregir', 'devuelto', 'devolucion_rechazada'];

    public function up(): void
    {
        Schema::table('prestamos', function (Blueprint $table) {
            $table->enum('estado', self::AHORA)->default('pendiente')->change();
            $table->text('motivo_no_entrega')->nullable()->after('motivo_cancelacion');
            $table->foreignId('no_entregado_por_id')->nullable()->after('motivo_no_entrega')->constrained('users')->nullOnDelete();
            $table->dateTime('no_entregado_en')->nullable()->after('no_entregado_por_id');
        });
    }

    public function down(): void
    {
        DB::table('prestamos')->whereIn('estado', ['entregado', 'por_corregir', 'devolucion_rechazada'])->update(['estado' => 'devuelto']);
        DB::table('prestamos')->whereIn('estado', ['no_recogido', 'no_entregado'])->update(['estado' => 'cancelado']);

        Schema::table('prestamos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('no_entregado_por_id');
            $table->dropColumn(['motivo_no_entrega', 'no_entregado_en']);
            $table->enum('estado', self::ANTES)->default('pendiente')->change();
        });
    }
};
