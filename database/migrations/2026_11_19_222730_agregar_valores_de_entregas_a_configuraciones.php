<?php

use App\Models\Configuracion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Valores nuevos de Configuración para entregas y devoluciones: anticipación para entregar,
 * tiempo máximo de devolución y máximo de fotos. Solo se agregan los que no existan
 * (en una base nueva ya los crea la migración de configuraciones).
 */
return new class extends Migration
{
    private const CLAVES = ['anticipacion_entrega', 'tiempo_devolucion', 'max_fotos'];

    public function up(): void
    {
        $ahora = now();
        $existentes = DB::table('configuraciones')->whereIn('clave', self::CLAVES)->pluck('clave')->all();

        DB::table('configuraciones')->insert(collect(self::CLAVES)
            ->reject(fn ($clave) => in_array($clave, $existentes, true))
            ->map(fn ($clave) => ['clave' => $clave, 'valor' => Configuracion::VALORES[$clave][3], 'created_at' => $ahora, 'updated_at' => $ahora])
            ->values()->all());
    }

    public function down(): void
    {
        DB::table('configuraciones')->whereIn('clave', self::CLAVES)->delete();
    }
};
