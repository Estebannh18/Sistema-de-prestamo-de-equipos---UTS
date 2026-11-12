<?php

namespace App\Http\Controllers;

use App\Models\Equipo;
use App\Models\Prestamo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PrestamoController extends Controller
{
    /**
     * Listado de préstamos.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $prestamos = Prestamo::with(['equipo', 'solicitante'])->latest()
            ->when($user->esDocente() || $user->esEstudiante(), fn ($query) => $query->where('solicitante_id', $user->id))
            ->get();

        return view('prestamos.index', ['prestamos' => $prestamos]);
    }

    /**
     * Pestaña Solicitud.
     */
    public function create(Request $request)
    {
        Gate::authorize('create', Prestamo::class);

        $equipos = Equipo::with('categoria')->disponiblesPara($request->user())->orderBy('codigo_inventario')->get();

        return view('prestamos.create', ['equipos' => $equipos]);
    }

    /**
     * Registra una solicitud.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', Prestamo::class);

        abort(501, 'Pendiente: se implementa en la fase de este módulo.');
    }

    /**
     * Detalle de un préstamo.
     */
    public function show(Prestamo $prestamo)
    {
        Gate::authorize('view', $prestamo);

        abort(501, 'Pendiente: se implementa en la fase de este módulo.');
    }

    /**
     * Aprueba una solicitud.
     */
    public function aprobar(Prestamo $prestamo)
    {
        Gate::authorize('atender', $prestamo);

        abort(501, 'Pendiente: se implementa en la fase de este módulo.');
    }

    /**
     * Rechaza una solicitud.
     */
    public function rechazar(Prestamo $prestamo)
    {
        Gate::authorize('atender', $prestamo);

        abort(501, 'Pendiente: se implementa en la fase de este módulo.');
    }

    /**
     * Registra la devolución de un préstamo.
     */
    public function devolver(Prestamo $prestamo)
    {
        Gate::authorize('atender', $prestamo);

        abort(501, 'Pendiente: se implementa en la fase de este módulo.');
    }
}
