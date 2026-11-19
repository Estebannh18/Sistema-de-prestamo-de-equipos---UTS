<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Las carreras de un espacio dejan de ser «recomendadas» y pasan a ser «habilitadas»:
     * los estudiantes de esas carreras pueden pedirlo; los docentes, cualquier espacio.
     * Un espacio con carreras queda para «ambos»; uno sin carreras, solo para docentes.
     */
    public function up(): void
    {
        $espacios = DB::table('equipos')
            ->whereIn('categoria_id', DB::table('categorias')->where('tipo', 'espacio')->select('id'));

        (clone $espacios)->whereIn('id', DB::table('equipo_programa')->select('equipo_id'))->update(['solicitable_por' => 'ambos']);
        (clone $espacios)->whereNotIn('id', DB::table('equipo_programa')->select('equipo_id'))->update(['solicitable_por' => 'docente']);
    }

    /**
     * Vuelve a dejar los espacios solo para docentes, como estaban.
     */
    public function down(): void
    {
        DB::table('equipos')
            ->whereIn('categoria_id', DB::table('categorias')->where('tipo', 'espacio')->select('id'))
            ->update(['solicitable_por' => 'docente']);
    }
};
