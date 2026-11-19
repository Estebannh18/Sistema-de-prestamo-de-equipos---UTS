<?php

namespace App\Http\Controllers;

use App\Http\Requests\HorarioRequest;
use App\Models\Equipo;
use App\Models\Horario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Horarios de un espacio o equipo: los bloques de horas en que se presta, por día (administrador).
 */
class HorarioController extends Controller
{
    public function edit(Equipo $equipo): View
    {
        $equipo->load(['categoria', 'horarios']);

        // Bloques por día para el formulario: los del intento anterior si hubo errores, o los guardados.
        $bloques = [];
        foreach (Horario::DIAS as $dia => $nombre) {
            $bloques[$dia] = old("bloques.{$dia}", $equipo->horarios->where('dia_semana', $dia)
                ->map(fn (Horario $h) => ['inicio' => $h->inicio(), 'fin' => $h->fin()])->values()->all());
        }

        return view('horarios.edit', ['equipo' => $equipo, 'bloques' => $bloques]);
    }

    /**
     * Reemplaza todos los bloques del espacio o equipo. Los préstamos ya hechos no cambian:
     * cada préstamo guarda su propia hora de inicio y de fin.
     */
    public function update(HorarioRequest $request, Equipo $equipo): RedirectResponse
    {
        $bloques = (array) $request->validated('bloques', []);

        DB::transaction(function () use ($equipo, $bloques) {
            $equipo->horarios()->delete();
            foreach ($bloques as $dia => $filas) {
                foreach ($filas as $fila) {
                    $equipo->horarios()->create(['dia_semana' => (int) $dia, 'hora_inicio' => $fila['inicio'], 'hora_fin' => $fila['fin']]);
                }
            }
        });

        $total = collect($bloques)->flatten(1)->count();

        return redirect()->route('horarios.edit', $equipo)->with('status', $total === 0
            ? "Se quitaron todos los horarios de {$equipo->nombre}: no se podrá solicitar hasta que tenga bloques."
            : "Horarios de {$equipo->nombre} guardados: {$total} ".($total === 1 ? 'bloque' : 'bloques').' en la semana.');
    }
}
