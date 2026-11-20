<?php

namespace App\Http\Controllers;

use App\Http\Requests\DevolucionRequest;
use App\Models\Configuracion;
use App\Models\Devolucion;
use App\Models\Prestamo;
use App\Services\EntregaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Módulo Devoluciones (funcionario y administrador): por recibir (las atrasadas resaltadas),
 * por corregir, con pendiente e historial. Formulario de cada revisión y «Marcar como resuelto».
 */
class DevolucionController extends Controller
{
    /** Pestañas: clave => etiqueta. */
    public const PESTANAS = [
        'por_recibir' => 'Por recibir',
        'por_corregir' => 'Por corregir',
        'con_pendiente' => 'Con pendiente',
        'historial' => 'Historial',
    ];

    public function __construct(private EntregaService $servicio) {}

    public function index(Request $request): View
    {
        $pestana = array_key_exists((string) $request->query('ver'), self::PESTANAS) ? $request->query('ver') : 'por_recibir';
        $buscar = mb_substr(trim((string) $request->query('buscar')), 0, 80);
        $conPendiente = fn (Builder $q) => $q->whereNotNull('pendiente')->whereNull('pendiente_resuelto_en')->where('resultado', 'aceptada');

        $consultas = [
            'por_recibir' => fn (Builder $q) => $q->where('estado', 'entregado')->orderBy('fecha')->orderBy('hora_fin'),
            'por_corregir' => fn (Builder $q) => $q->where('estado', 'por_corregir')->orderBy('fecha')->orderBy('hora_fin'),
            'con_pendiente' => fn (Builder $q) => $q->where('estado', 'devuelto')->whereHas('ultimaDevolucion', $conPendiente)->orderByDesc('fecha'),
            'historial' => fn (Builder $q) => $q->has('devoluciones')
                ->orderByDesc(Devolucion::select('recibido_en')->whereColumn('prestamo_id', 'prestamos.id')->latest('recibido_en')->limit(1)),
        ];
        $base = fn () => Prestamo::query()->when($buscar !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
            ->whereHas('solicitante', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$buscar}%")->orWhere('email', 'like', "%{$buscar}%")->orWhere('documento', 'like', "%{$buscar}%")))
            ->orWhereHas('equipo', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('nombre', 'like', "%{$buscar}%")->orWhere('codigo_inventario', 'like', "%{$buscar}%")))));

        return view('devoluciones.index', [
            'prestamos' => $consultas[$pestana]($base()->with(['equipo.categoria', 'solicitante', 'entrega', 'ultimaDevolucion.funcionario']))
                ->paginate(15)->withQueryString(),
            'pestana' => $pestana,
            'buscar' => $buscar,
            'conteos' => collect($consultas)->map(fn ($consulta) => $consulta($base())->reorder()->count()),
        ]);
    }

    public function create(Prestamo $prestamo): View
    {
        return view('devoluciones.create', [
            'prestamo' => $prestamo->load(['equipo.categoria', 'solicitante', 'entrega.funcionario', 'entrega.fotos', 'devoluciones.funcionario', 'devoluciones.fotos']),
            'posteriores' => $this->servicio->posteriores($prestamo),
        ]);
    }

    public function store(DevolucionRequest $request, Prestamo $prestamo): RedirectResponse
    {
        $devolucion = $this->servicio->recibir($prestamo, $request->user(), $request->safe()->except('fotos'), $request->file('fotos', []));

        $nombre = "«{$prestamo->equipo->nombre}»";
        $mensaje = match ($devolucion->resultado) {
            'aceptada' => "Recibiste {$nombre} de {$prestamo->solicitante->name}.".($devolucion->pendiente ? ' Quedó un pendiente.' : ''),
            'por_corregir' => "Marcaste la devolución de {$nombre} como por corregir. Se le avisó a {$prestamo->solicitante->name} con el motivo.",
            default => "Rechazaste la devolución de {$nombre}: quedó fuera de servicio y se cancelaron sus solicitudes posteriores.",
        };
        if ($devolucion->conRetraso()) {
            $mensaje .= ' Se devolvió con '.Configuracion::duracion($devolucion->minutos_retraso).' de retraso.';
        }

        return redirect()->route('devoluciones.index', $devolucion->resultado === 'por_corregir' ? ['ver' => 'por_corregir'] : [])->with('status', $mensaje);
    }

    public function resolverPendiente(Request $request, Devolucion $devolucion): RedirectResponse
    {
        $this->servicio->resolverPendiente($devolucion, $request->user());

        return back()->with('status', "Marcaste como resuelto el pendiente de «{$devolucion->prestamo->equipo->nombre}».");
    }
}
