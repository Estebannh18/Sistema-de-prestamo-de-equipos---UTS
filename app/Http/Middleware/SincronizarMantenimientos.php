<?php

namespace App\Http\Middleware;

use App\Models\Mantenimiento;
use App\Models\Prestamo;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mantiene los estados al día sin una tarea programada en el servidor:
 * - una vez al día, pone el estado de los equipos según los mantenimientos de hoy
 *   (un mantenimiento programado para el viernes empieza a verse el viernes);
 * - como mucho una vez por minuto, pasa a «No recogido» las aprobadas cuyo bloque terminó sin entrega.
 */
class SincronizarMantenimientos
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Cache::add('mantenimientos-sincronizados:'.today()->toDateString(), true, today()->addDay())) {
            Mantenimiento::sincronizarEstados();
        }

        if (Cache::add('no-recogidos:'.now()->format('Y-m-d H:i'), true, 120)) {
            Prestamo::marcarNoRecogidos();
        }

        return $next($request);
    }
}
