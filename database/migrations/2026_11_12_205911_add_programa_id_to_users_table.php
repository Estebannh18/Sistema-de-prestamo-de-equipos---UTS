<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Antes había un solo campo de texto, «programa_dependencia», para todo.
     * Ahora:
     *  - estudiantes y docentes eligen su programa de la tabla programas (programa_id);
     *  - el personal escribe su dependencia en «dependencia» (el mismo campo de antes, renombrado).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('programa_dependencia', 'dependencia');
        });

        Schema::table('users', function (Blueprint $table) {
            // restrictOnDelete: no se puede borrar un programa que tenga usuarios.
            $table->foreignId('programa_id')->nullable()->after('telefono')->constrained('programas')->restrictOnDelete();
        });

        // Usuarios que ya existían: si el texto que tenían coincide con un solo programa, se enlazan a él.
        // Si no coincide (o coincide con varias sedes), quedan sin programa y el administrador lo elige al editarlos.
        $roles = ['estudiante', 'docente'];
        foreach (DB::table('users')->whereIn('rol', $roles)->whereNotNull('dependencia')->get(['id', 'dependencia']) as $usuario) {
            $programas = DB::table('programas')->where('nombre', trim($usuario->dependencia))->pluck('id');
            if ($programas->count() === 1) {
                DB::table('users')->where('id', $usuario->id)->update(['programa_id' => $programas->first()]);
            }
        }
        DB::table('users')->whereIn('rol', $roles)->update(['dependencia' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Se devuelve el nombre del programa al campo de texto, para no perder el dato.
        foreach (DB::table('users')->whereNotNull('programa_id')->get(['id', 'programa_id']) as $usuario) {
            DB::table('users')->where('id', $usuario->id)->update([
                'dependencia' => DB::table('programas')->where('id', $usuario->programa_id)->value('nombre'),
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('programa_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('dependencia', 'programa_dependencia');
        });
    }
};
