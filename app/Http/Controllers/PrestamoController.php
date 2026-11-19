<?php

namespace App\Http\Controllers;

use App\Http\Requests\RechazoRequest;
use App\Http\Requests\SolicitudRequest;
use App\Models\Categoria;
use App\Models\Configuracion;
use App\Models\Equipo;
use App\Models\Horario;
use App\Models\Prestamo;
use App\Services\PrestamoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PrestamoController extends Controller
{
    public function __construct(private PrestamoService $servicio) {}

    /**
     * Estudiantes y docentes: «Mis solicitudes». Personal: todos los préstamos, con filtros.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $esPersonal = $user->esAdministrador() || $user->esFuncionario();
        $estado = in_array($request->query('estado'), ['pendiente', 'aprobado', 'rechazado', 'cancelado', 'devuelto'], true) ? $request->query('estado') : null;

        $prestamos = Prestamo::with(['equipo.categoria', 'solicitante.programa', 'funcionario'])
            ->when(! $esPersonal, fn ($query) => $query->where('solicitante_id', $user->id))
            ->when($estado, fn ($query) => $query->where('estado', $estado))
            ->orderByDesc('fecha')->orderByDesc('hora_inicio')->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('prestamos.index', ['prestamos' => $prestamos, 'esPersonal' => $esPersonal, 'estadoActual' => $estado]);
    }

    /**
     * Pestaña Solicitud: un formulario por pasos. Lo que la persona ya eligió viaja en la dirección
     * (fecha, tipo, categoria, q, equipo), así cada paso se puede abrir, recargar o compartir:
     *   1. día · 2. equipo o espacio · 3. categoría o búsqueda · 4. cuál · 5. bloque y confirmar.
     * Si llega con ?equipo (desde «Equipos y salones»), el tipo y la categoría salen de él.
     */
    public function create(Request $request): View
    {
        Gate::authorize('create', Prestamo::class);
        $user = $request->user();

        // La solicitud de los docentes tendrá sus propias reglas: se deja para una etapa siguiente.
        if (! $user->can('solicitar', Prestamo::class)) {
            return view('prestamos.create', ['paraDocentes' => true]);
        }

        $fechas = $this->servicio->fechasSolicitables();
        $fecha = $fechas->first(fn (Carbon $f) => $f->toDateString() === $request->query('fecha'));

        $equipo = $request->filled('equipo') ? Equipo::with(['categoria', 'programas'])->find($request->query('equipo')) : null;
        if ($equipo) {
            abort_unless($equipo->permiteSolicitud($user) && $equipo->estaDisponible(), 403);
        }

        $tipo = $equipo ? ($equipo->esEspacio() ? 'espacio' : 'equipo')
            : (in_array($request->query('tipo'), ['equipo', 'espacio'], true) ? $request->query('tipo') : null);
        $buscar = $tipo ? mb_substr(trim((string) $request->query('q')), 0, 80) : '';
        $todas = $tipo && ! $equipo && $request->query('categoria') === 'todas';
        $categoria = $equipo?->categoria
            ?? ($tipo && ! $todas && $request->filled('categoria') ? Categoria::where('tipo', $tipo)->find($request->query('categoria')) : null);
        $soloLibres = $request->boolean('libres');

        $paso = match (true) {
            ! $fecha => 'dia',
            ! $tipo => 'que',
            ! $equipo && ! $categoria && ! $todas && $buscar === '' => 'categoria',
            ! $equipo => 'lista',
            default => 'horario',
        };

        $datos = compact('paso', 'fechas', 'fecha', 'equipo', 'tipo', 'buscar', 'todas', 'categoria', 'soloLibres') + ['paraDocentes' => false];

        if ($paso === 'dia' && $equipo) {
            $datos['diasConHorario'] = $equipo->horarios()->distinct()->pluck('dia_semana')->all();
        }

        if ($paso === 'que') {
            $datos['tipos'] = collect(['equipo' => false, 'espacio' => true])->map(function (bool $espacio) use ($user, $fecha) {
                $anticipacion = Configuracion::minutosAnticipacion($user, $espacio);
                $categorias = $this->servicio->categoriasPara($user, $espacio);

                return [
                    'cantidad' => $categorias->sum('opciones_count'),
                    'ejemplos' => $categorias->sortByDesc('opciones_count')->pluck('nombre')->take(3)->all(),
                    'anticipacion' => $anticipacion,
                    // Si ni el final del día alcanza la anticipación mínima, ese día ya no se puede pedir.
                    'alcanza' => $fecha->copy()->endOfDay()->greaterThan(now()->addMinutes($anticipacion)),
                ];
            });
        }

        if (in_array($paso, ['categoria', 'lista'], true)) {
            $datos['categorias'] = $this->servicio->categoriasPara($user, $tipo === 'espacio');
        }

        if ($paso === 'lista') {
            $opciones = $this->servicio->opciones($user, $fecha, $tipo === 'espacio', $categoria?->id, $buscar)
                ->when($soloLibres, fn ($opciones) => $opciones->where('libres', '>', 0)->values());
            $pagina = LengthAwarePaginator::resolveCurrentPage();
            $datos['resultados'] = new LengthAwarePaginator($opciones->forPage($pagina, 12)->values(), $opciones->count(), 12, $pagina, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);
        }

        if ($paso === 'horario') {
            abort_unless($equipo, 404);
            $datos += [
                'bloques' => $this->servicio->bloquesDelDia($equipo, $fecha, $user),
                'revision' => $this->servicio->revisar($equipo, $user),
                'horarioElegido' => (int) $request->query('horario'),
                'diasConHorario' => $equipo->horarios()->distinct()->pluck('dia_semana')->all(),
            ];
        }

        return view('prestamos.create', $datos);
    }

    /**
     * Registra una solicitud. Si la persona ya tiene préstamos en curso y no ha visto el aviso,
     * vuelve a la misma pantalla con el aviso para que decida.
     */
    public function store(SolicitudRequest $request): RedirectResponse
    {
        $user = $request->user();
        $equipo = Equipo::with('categoria')->findOrFail($request->validated('equipo_id'));
        $fecha = Carbon::createFromFormat('Y-m-d', $request->validated('fecha'))->startOfDay();
        $horario = Horario::findOrFail($request->validated('horario_id'));
        $parametros = ['equipo' => $equipo->id, 'fecha' => $fecha->toDateString(), 'horario' => $horario->id];

        if ($this->servicio->revisar($equipo, $user)['avisar'] && ! $request->boolean('confirmado')) {
            return redirect()->route('prestamos.create', $parametros)->with('confirmar', true);
        }

        $prestamo = $this->servicio->solicitar($user, $equipo, $fecha, $horario, $request->boolean('confirmado'));

        $que = "«{$equipo->nombre}» para el {$prestamo->fechaLarga()}, de {$prestamo->bloque()}";

        return redirect()->route('prestamos.index')->with('status', $prestamo->estado === 'aprobado'
            ? "¡Listo! Tu préstamo de {$que} quedó aprobado."
            : "Tu solicitud de {$que} quedó pendiente de aprobación. Te avisaremos cuando el funcionario responda.");
    }

    /**
     * Detalle de una solicitud. Al personal le muestra las otras solicitudes del mismo bloque y los botones para atenderla.
     */
    public function show(Request $request, Prestamo $prestamo): View
    {
        Gate::authorize('view', $prestamo);
        $prestamo->load(['equipo.categoria', 'solicitante.programa', 'funcionario']);
        $user = $request->user();

        return view('prestamos.show', [
            'prestamo' => $prestamo,
            'esPersonal' => $user->esAdministrador() || $user->esFuncionario(),
            'choques' => $user->can('atender', $prestamo) ? $this->servicio->choques($prestamo) : collect(),
        ]);
    }

    /**
     * Solicitudes por aprobar (funcionario y administrador). Las pendientes, de la más antigua a la más reciente.
     */
    public function pendientes(Request $request): View
    {
        Gate::authorize('atender', Prestamo::class);

        $tipo = in_array($request->query('tipo'), ['espacio', 'equipo'], true) ? $request->query('tipo') : null;
        $fecha = rescue(fn () => $request->filled('fecha') ? Carbon::createFromFormat('Y-m-d', $request->query('fecha'))->toDateString() : null, null, false);

        $pendientes = Prestamo::with(['equipo.categoria', 'solicitante.programa'])
            ->where('estado', 'pendiente')
            ->when($tipo, fn ($query) => $query->deTipo($tipo === 'espacio'))
            ->when($fecha, fn ($query) => $query->whereDate('fecha', $fecha))
            ->orderBy('created_at')->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('prestamos.pendientes', [
            'pendientes' => $pendientes,
            'choques' => $pendientes->getCollection()->mapWithKeys(fn (Prestamo $p) => [$p->id => $this->servicio->choques($p)]),
            'tipoActual' => $tipo,
            'fechaActual' => $fecha,
        ]);
    }

    /**
     * Aprueba una solicitud pendiente. Las que se cruzan con ella se rechazan solas.
     */
    public function aprobar(Request $request, Prestamo $prestamo): RedirectResponse
    {
        Gate::authorize('atender', $prestamo);
        $rechazadas = $this->servicio->choques($prestamo)->where('estado', 'pendiente')->count();

        $this->servicio->aprobar($prestamo, $request->user());

        $mensaje = "Aprobaste la solicitud de {$prestamo->solicitante->name} para «{$prestamo->equipo->nombre}».";
        if ($rechazadas > 0) {
            $mensaje .= " Se rechazaron automáticamente {$rechazadas} ".($rechazadas === 1 ? 'solicitud que se cruzaba' : 'solicitudes que se cruzaban').' y se les avisó.';
        }

        return redirect()->route('prestamos.pendientes')->with('status', $mensaje);
    }

    /**
     * Rechaza una solicitud pendiente, con el motivo que verá el solicitante.
     */
    public function rechazar(RechazoRequest $request, Prestamo $prestamo): RedirectResponse
    {
        $this->servicio->rechazar($prestamo, $request->user(), $request->validated('motivo'));

        return redirect()->route('prestamos.pendientes')->with('status', "Rechazaste la solicitud de {$prestamo->solicitante->name} para «{$prestamo->equipo->nombre}». Se le avisó con el motivo.");
    }

    /**
     * El solicitante cancela su solicitud dentro del tiempo permitido.
     * Si viene del aviso de máximo (?volver=…), regresa a esa solicitud para enviarla.
     */
    public function cancelar(Request $request, Prestamo $prestamo): RedirectResponse
    {
        Gate::authorize('cancelar', $prestamo);
        $this->servicio->cancelar($prestamo, $request->user());

        $mensaje = "Cancelaste la solicitud de «{$prestamo->equipo->nombre}» del {$prestamo->fechaLarga()}.";
        $volver = $request->input('volver');

        return is_array($volver) && isset($volver['equipo'])
            ? redirect()->route('prestamos.create', array_intersect_key($volver, array_flip(['equipo', 'fecha', 'horario'])))->with('status', $mensaje.' Ahora puedes enviar la nueva solicitud.')
            : redirect()->route('prestamos.index')->with('status', $mensaje);
    }

    /**
     * Registra la devolución de un préstamo. Se hace en el módulo Devoluciones.
     */
    public function devolver(Prestamo $prestamo)
    {
        Gate::authorize('atender', $prestamo);

        abort(501, 'Pendiente: se implementa en el módulo Devoluciones.');
    }
}
