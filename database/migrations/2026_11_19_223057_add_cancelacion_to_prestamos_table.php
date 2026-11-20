<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quién canceló una solicitud y por qué: el solicitante, el personal (funcionario o administrador,
 * con motivo) o el sistema (mantenimiento o equipo dañado, con motivo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prestamos', function (Blueprint $table) {
            $table->enum('cancelada_por', ['solicitante', 'personal', 'sistema'])->nullable()->after('cancelado_en');
            $table->foreignId('cancelado_por_id')->nullable()->after('cancelada_por')->constrained('users')->nullOnDelete();
            $table->text('motivo_cancelacion')->nullable()->after('cancelado_por_id');
        });

        // Las canceladas hasta hoy las canceló su solicitante.
        DB::table('prestamos')->where('estado', 'cancelado')->update(['cancelada_por' => 'solicitante', 'cancelado_por_id' => DB::raw('solicitante_id')]);
    }

    public function down(): void
    {
        Schema::table('prestamos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelado_por_id');
            $table->dropColumn(['cancelada_por', 'motivo_cancelacion']);
        });
    }
};
