<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\Programa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EquipoController extends Controller
{
    /**
     * Lista los equipos y los filtra por tipo (equipos o espacios), nombre o código, categoría y estado
     * (RF-05, RF-06, RF-07). Estudiantes y docentes solo ven los disponibles habilitados para su rol.
     * El resultado se agrupa por categoría.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $esPersonal = $user->esAdministrador() || $user->esFuncionario();
        $buscar = trim((string) $request->query('buscar'));
        $tipo = in_array($request->query('tipo'), ['equipo', 'espacio'], true) ? $request->query('tipo') : null;
        $categoria = $request->query('categoria');
        $estado = $esPersonal ? $request->query('estado') : null;

        // Todo menos el estado: sobre esta base se cuentan los totales por estado del resumen.
        $base = Equipo::query()
            ->when(! $esPersonal, fn ($query) => $query->disponiblesPara($user))
            ->when($tipo, fn ($query) => $query->whereHas('categoria', fn ($query) => $query->where('tipo', $tipo)))
            ->when($buscar !== '', fn ($query) => $query->where(function ($query) use ($buscar) {
                $query->where('nombre', 'like', "%{$buscar}%")
                    ->orWhere('codigo_inventario', 'like', "%{$buscar}%")
                    ->orWhere('marca', 'like', "%{$buscar}%")
                    ->orWhere('modelo', 'like', "%{$buscar}%")
                    ->orWhere('ubicacion', 'like', "%{$buscar}%")
                    ->orWhereHas('programas', fn ($query) => $query->where('nombre', 'like', "%{$buscar}%"));
            }))
            ->when($categoria, fn ($query) => $query->where('categoria_id', $categoria));

        $porEstado = (clone $base)->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');

        $equipos = (clone $base)
            ->with(['categoria', 'programas'])
            ->when($user->esAdministrador(), fn ($query) => $query->withCount('prestamos'))
            ->when($estado, fn ($query) => $query->where('estado', $estado))
            ->orderBy('codigo_inventario')
            ->get();

        return view('equipos.index', [
            'grupos' => $equipos->groupBy('categoria_id')->sortBy(fn ($grupo) => $grupo->first()->categoria->nombre),
            'total' => $equipos->count(),
            'porEstado' => $porEstado,
            'esPersonal' => $esPersonal,
            'categorias' => Categoria::when($tipo, fn ($query) => $query->where('tipo', $tipo))->orderBy('nombre')->get(),
            'espacios' => Categoria::where('tipo', 'espacio')->orderBy('nombre')->get(),
            'buscar' => $buscar,
            'tipoActual' => $tipo,
            'categoriaActual' => $categoria,
            'estadoActual' => $estado,
        ]);
    }

    /**
     * Muestra el formulario para crear un equipo o un espacio. Con ?categoria=id llega con esa
     * categoría elegida (así se abre "Crear espacio"); con ?tipo=espacio, como espacio sin categoría.
     */
    public function create(Request $request): View
    {
        $categoria = Categoria::find($request->query('categoria'));
        $tipo = $categoria?->tipo ?? ($request->query('tipo') === 'espacio' ? 'espacio' : 'equipo');

        return $this->formulario('equipos.create', null, $tipo, $categoria?->id);
    }

    public function store(Request $request): RedirectResponse
    {
        $categoria = $this->categoriaElegida($request);
        $datos = $request->validate($this->reglas($categoria));

        $equipo = Equipo::create($this->normalizar($datos, $categoria));
        $this->guardarCarreras($equipo, $categoria, $datos);

        return redirect()->route('equipos.index')->with('status', $categoria->esEspacio() ? 'Espacio creado.' : 'Equipo creado.');
    }

    /**
     * Muestra un equipo. Un estudiante o un docente solo abre los disponibles habilitados para su rol.
     */
    public function show(Request $request, Equipo $equipo): View
    {
        $user = $request->user();

        if ($user->esDocente() || $user->esEstudiante()) {
            abort_unless($equipo->estaDisponible() && $equipo->permiteSolicitud($user), 403);
        }

        return view('equipos.show', ['equipo' => $equipo->load([
            'categoria', 'programas', 'horarios',
            // Mantenimientos actuales y próximos.
            'mantenimientos' => fn ($query) => $query->where(fn ($query) => $query->whereNull('hasta')->orWhereDate('hasta', '>=', today()->toDateString()))->with('creadoPor'),
        ])]);
    }

    public function edit(Equipo $equipo): View
    {
        $equipo->load(['categoria', 'programas']);

        return $this->formulario('equipos.edit', $equipo, $equipo->categoria->tipo, $equipo->categoria_id);
    }

    /**
     * Actualiza un equipo o un espacio. El estado no se cambia aquí (RF-17): lo ponen el mantenimiento
     * programado, la entrega y la devolución.
     */
    public function update(Request $request, Equipo $equipo): RedirectResponse
    {
        $categoria = $this->categoriaElegida($request);
        $datos = $request->validate($this->reglas($categoria, $equipo));

        $equipo->update($this->normalizar($datos, $categoria));
        $this->guardarCarreras($equipo, $categoria, $datos);

        return redirect()->route('equipos.index')->with('status', $categoria->esEspacio() ? 'Espacio actualizado.' : 'Equipo actualizado.');
    }

    /**
     * Elimina un equipo, salvo que tenga préstamos (RF-16).
     */
    public function destroy(Equipo $equipo): RedirectResponse
    {
        if ($equipo->prestamos()->exists()) {
            return back()->with('error', 'No se puede eliminar un equipo que tiene préstamos.');
        }

        $equipo->delete();

        return redirect()->route('equipos.index')->with('status', 'Equipo eliminado.');
    }

    /**
     * Formulario de creación o edición. Solo ofrece las categorías del mismo tipo:
     * un equipo no se puede mover a una categoría de espacios, ni al revés.
     */
    private function formulario(string $vista, ?Equipo $equipo, string $tipo, ?int $categoriaActual): View
    {
        return view($vista, [
            'equipo' => $equipo,
            'tipo' => $tipo,
            'categorias' => Categoria::where('tipo', $tipo)->orderBy('nombre')->get(),
            'categoriaActual' => $categoriaActual,
            'carreras' => $tipo === 'espacio' ? Programa::orderBy('nombre')->distinct()->pluck('nombre') : collect(),
        ]);
    }

    /**
     * La categoría se valida primero, porque su tipo decide qué otros campos se piden.
     */
    private function categoriaElegida(Request $request): Categoria
    {
        $request->validate(['categoria_id' => ['required', Rule::exists(Categoria::class, 'id')]]);

        return Categoria::findOrFail($request->input('categoria_id'));
    }

    /**
     * Equipos: marca, modelo y quién lo pide. Espacios: ubicación, capacidad y carreras habilitadas;
     * quién lo pide sale de las carreras (sin carreras, solo docentes), así que no se pregunta.
     *
     * @return array<string, array<int, mixed>>
     */
    private function reglas(Categoria $categoria, ?Equipo $equipo = null): array
    {
        $comunes = [
            'categoria_id' => ['required', Rule::exists(Categoria::class, 'id')],
            'codigo_inventario' => ['required', 'string', 'max:50', Rule::unique(Equipo::class)->ignore($equipo)],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
        ];

        if ($categoria->esEspacio()) {
            return $comunes + [
                'ubicacion' => ['nullable', 'string', 'max:100'],
                'capacidad' => ['nullable', 'integer', 'min:1', 'max:1000'],
                'carreras' => ['nullable', 'array'],
                'carreras.*' => ['string', Rule::exists(Programa::class, 'nombre')],
            ];
        }

        return $comunes + [
            'marca' => ['nullable', 'string', 'max:255'],
            'modelo' => ['nullable', 'string', 'max:255'],
            'solicitable_por' => ['required', Rule::in(['estudiante', 'docente', 'ambos'])],
        ];
    }

    /**
     * Deja vacíos los campos que no corresponden al tipo, por si el equipo cambió de categoría.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function normalizar(array $datos, Categoria $categoria): array
    {
        $conCarreras = ! empty($datos['carreras']);
        unset($datos['carreras']);

        if ($categoria->esEspacio()) {
            // Con carreras lo piden docentes y los estudiantes de esas carreras; sin carreras, solo docentes.
            return ['marca' => null, 'modelo' => null, 'solicitable_por' => $conCarreras ? 'ambos' : 'docente'] + $datos;
        }

        return ['ubicacion' => null, 'capacidad' => null] + $datos;
    }

    /**
     * Guarda las carreras habilitadas de un espacio. Se eligen por nombre y se enlazan
     * todas las sedes de cada programa, para que sirva a los estudiantes de cualquier sede.
     *
     * @param  array<string, mixed>  $datos
     */
    private function guardarCarreras(Equipo $equipo, Categoria $categoria, array $datos): void
    {
        $nombres = $categoria->esEspacio() ? ($datos['carreras'] ?? []) : [];

        $equipo->programas()->sync(Programa::whereIn('nombre', $nombres)->pluck('id'));
    }
}
