<?php

namespace App\Http\Controllers;

use App\Http\Requests\EntregaRequest;
use App\Models\Entrega;
use App\Models\Prestamo;
use App\Services\EntregaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Módulo Entregas (funcionario y administrador): las aprobadas de hoy y las próximas, los
 * no recogidos, los no entregados y el historial. Formulario de entrega y «No se pudo entregar».
 */
class EntregaController extends Controller
{
    /** Pestañas: clave => etiqueta. */
    public const PESTANAS = [
        'hoy' => 'Hoy',
        'proximas' => 'Próximas',
        'no_recogidos' => 'No recogidos',
        'no_entregados' => 'No entregados',
        'historial' => 'Entregadas',
    ];

    public function __construct(private EntregaService $servicio) {}

    public function index(Request $request): View
    {
        $pestana = array_key_exists((string) $request->query('ver'), self::PESTANAS) ? $request->query('ver') : 'hoy';
        $buscar = mb_substr(trim((string) $request->query('buscar')), 0, 80);
        $hoy = today()->toDateString();

        $consultas = [
            'hoy' => fn (Builder $q) => $q->where('estado', 'aprobado')->whereDate('fecha', $hoy)->orderBy('hora_inicio')->orderBy('id'),
            'proximas' => fn (Builder $q) => $q->where('estado', 'aprobado')->whereDate('fecha', '>', $hoy)->orderBy('fecha')->orderBy('hora_inicio'),
            'no_recogidos' => fn (Builder $q) => $q->where('estado', 'no_recogido')->orderByDesc('fecha')->orderByDesc('hora_inicio'),
            'no_entregados' => fn (Builder $q) => $q->where('estado', 'no_entregado')->orderByDesc('no_entregado_en'),
            'historial' => fn (Builder $q) => $q->has('entrega')->orderByDesc(Entrega::select('entregado_en')->whereColumn('prestamo_id', 'prestamos.id')->limit(1)),
        ];
        $base = fn () => Prestamo::query()->when($buscar !== '', fn (Builder $q) => $this->buscar($q, $buscar));

        $prestamos = $consultas[$pestana]($base()->with(['equipo.categoria', 'solicitante.programa', 'entrega.funcionario']))
            ->paginate(15)->withQueryString();

        return view('entregas.index', [
            'prestamos' => $prestamos,
            'pestana' => $pestana,
            'buscar' => $buscar,
            'conteos' => collect($consultas)->map(fn ($consulta) => $consulta($base())->reorder()->count()),
            // En «Hoy» y «Próximas»: quién tiene todavía el equipo (préstamo anterior sin devolver).
            'sinDevolver' => in_array($pestana, ['hoy', 'proximas'], true)
                ? $prestamos->getCollection()->mapWithKeys(fn (Prestamo $p) => [$p->id => $this->servicio->sinDevolver($p)])
                : collect(),
        ]);
    }

    public function create(Prestamo $prestamo): View
    {
        return view('entregas.create', [
            'prestamo' => $prestamo->load(['equipo.categoria', 'solicitante.programa']),
            'sinDevolver' => $prestamo->estado === 'aprobado' ? $this->servicio->sinDevolver($prestamo) : null,
        ]);
    }

    public function store(EntregaRequest $request, Prestamo $prestamo): RedirectResponse
    {
        $this->servicio->entregar(
            $prestamo,
            $request->user(),
            $request->safe()->only(['estado', 'complementos', 'condiciones', 'observaciones']),
            $request->file('fotos', [])
        );

        $limite = $prestamo->limiteDevolucion()->locale('es')->isoFormat('h:mm a');

        return redirect()->route('entregas.index')
            ->with('status', "Entregaste «{$prestamo->equipo->nombre}» a {$prestamo->solicitante->name}. Debe devolverlo hasta las {$limite}");
    }

    public function noEntregado(Request $request, Prestamo $prestamo): RedirectResponse
    {
        $datos = $request->validate(
            ['motivo_no_entrega' => ['required', 'string', 'min:5', 'max:500']],
            [
                'motivo_no_entrega.required' => 'Escribe por qué no se pudo entregar: el solicitante lo verá en su notificación.',
                'motivo_no_entrega.min' => 'El motivo debe tener al menos 5 caracteres.',
                'motivo_no_entrega.max' => 'El motivo no puede tener más de 500 caracteres.',
            ]
        );

        $this->servicio->noSePudoEntregar($prestamo, $request->user(), trim($datos['motivo_no_entrega']));

        return redirect()->route('entregas.index')
            ->with('status', "Marcaste que no se pudo entregar «{$prestamo->equipo->nombre}» a {$prestamo->solicitante->name}. Se le avisó con el motivo.");
    }

    /** Búsqueda por solicitante (nombre, correo o documento) o por espacio o equipo (nombre o código). */
    private function buscar(Builder $query, string $buscar): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->whereHas('solicitante', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$buscar}%")->orWhere('email', 'like', "%{$buscar}%")->orWhere('documento', 'like', "%{$buscar}%")))
            ->orWhereHas('equipo', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('nombre', 'like', "%{$buscar}%")->orWhere('codigo_inventario', 'like', "%{$buscar}%"))));
    }
}
