<?php

namespace App\Http\Controllers;

use App\Http\Requests\MantenimientoRequest;
use App\Models\Equipo;
use App\Models\Mantenimiento;
use App\Models\Prestamo;
use App\Services\PrestamoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Mantenimiento programado de un espacio o equipo (administrador y funcionario).
 * En esas fechas nadie lo puede pedir. Si ya hay solicitudes en esas fechas, primero se avisa
 * cuántas son; al confirmar, se cancelan con el motivo y se le avisa a cada solicitante.
 */
class MantenimientoController extends Controller
{
    public function __construct(private PrestamoService $servicio) {}

    public function create(Equipo $equipo): View
    {
        return view('mantenimientos.create', ['equipo' => $equipo->load('categoria'), 'afectadas' => collect()]);
    }

    public function store(MantenimientoRequest $request, Equipo $equipo): View|RedirectResponse
    {
        $desde = $request->validated('desde');
        $hasta = $request->validated('hasta') ?: null;
        $motivo = $request->validated('motivo');

        if ($equipo->mantenimientos()->queSeCruzan($desde, $hasta)->exists()) {
            return back()->withInput()->withErrors(['desde' => "«{$equipo->nombre}» ya tiene un mantenimiento que se cruza con esas fechas. Termínalo o elige otras fechas."]);
        }

        // Si hay solicitudes en esas fechas, primero se muestra cuántas son y se pide confirmar.
        $afectadas = $this->afectadas($equipo, $desde, $hasta);
        // La confirmación vale solo para las fechas que se mostraron: si cambiaron, se vuelve a mostrar el aviso.
        if ($afectadas->isNotEmpty() && $request->input('confirmado') !== $desde.'|'.$hasta) {
            return view('mantenimientos.create', ['equipo' => $equipo->load('categoria'), 'afectadas' => $afectadas]);
        }

        $mantenimiento = DB::transaction(function () use ($request, $equipo, $desde, $hasta, $motivo) {
            Equipo::whereKey($equipo->id)->lockForUpdate()->first();
            $mantenimiento = $equipo->mantenimientos()->create(['desde' => $desde, 'hasta' => $hasta, 'motivo' => $motivo, 'creado_por_id' => $request->user()->id]);
            $this->servicio->cancelarPorElSistema(
                $this->afectadas($equipo, $desde, $hasta),
                "«{$equipo->nombre}» estará en mantenimiento {$mantenimiento->periodo()}. Motivo: {$motivo}"
            );

            return $mantenimiento;
        });
        Mantenimiento::sincronizarEstados();

        $mensaje = "Programaste el mantenimiento de «{$equipo->nombre}» {$mantenimiento->periodo()}.";
        if ($afectadas->isNotEmpty()) {
            $mensaje .= " Se cancelaron {$afectadas->count()} ".($afectadas->count() === 1 ? 'solicitud' : 'solicitudes').' y se le avisó a cada solicitante.';
        }

        return redirect()->route('equipos.show', $equipo)->with('status', $mensaje);
    }

    /**
     * Termina un mantenimiento: vuelve a estar disponible desde hoy. Si todavía no había empezado, se quita.
     */
    public function terminar(Mantenimiento $mantenimiento): RedirectResponse
    {
        $equipo = $mantenimiento->equipo;

        if ($mantenimiento->yaTermino()) {
            return back()->withErrors(['mantenimiento' => 'Ese mantenimiento ya terminó.']);
        }

        if ($mantenimiento->desde->greaterThanOrEqualTo(today())) {
            $mantenimiento->delete();
            $mensaje = "Quitaste el mantenimiento de «{$equipo->nombre}» {$mantenimiento->periodo()}.";
        } else {
            $mantenimiento->update(['hasta' => today()->subDay()]);
            $mensaje = "Terminaste el mantenimiento de «{$equipo->nombre}»: se puede pedir desde hoy.";
        }
        Mantenimiento::sincronizarEstados();

        return redirect()->route('equipos.show', $equipo)->with('status', $mensaje.' Las solicitudes que se cancelaron no vuelven: deben pedirse de nuevo.');
    }

    /**
     * Solicitudes pendientes o aprobadas de ese espacio o equipo en esas fechas, que todavía no terminan.
     *
     * @return Collection<int, Prestamo>
     */
    private function afectadas(Equipo $equipo, string $desde, ?string $hasta): Collection
    {
        return Prestamo::with(['solicitante', 'equipo'])
            ->where('equipo_id', $equipo->id)
            ->whereIn('estado', Prestamo::CANCELABLES)
            ->whereDate('fecha', '>=', $desde)
            ->when($hasta, fn ($query) => $query->whereDate('fecha', '<=', $hasta))
            ->orderBy('fecha')->orderBy('hora_inicio')
            ->get()
            ->filter(fn (Prestamo $prestamo) => $prestamo->fin()?->isFuture())
            ->values();
    }
}
