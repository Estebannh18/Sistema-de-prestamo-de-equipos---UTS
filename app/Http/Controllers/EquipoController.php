<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Equipo;
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
                    ->orWhere('modelo', 'like', "%{$buscar}%");
            }))
            ->when($categoria, fn ($query) => $query->where('categoria_id', $categoria));

        $porEstado = (clone $base)->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');

        $equipos = (clone $base)
            ->with('categoria')
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
            'salones' => Categoria::where('tipo', 'espacio')->orderBy('nombre')->value('id'),
            'buscar' => $buscar,
            'tipoActual' => $tipo,
            'categoriaActual' => $categoria,
            'estadoActual' => $estado,
        ]);
    }

    /**
     * Muestra el formulario para crear un equipo. Con ?categoria=id llega con esa categoría elegida,
     * que es como se abre "Crear salón".
     */
    public function create(Request $request): View
    {
        return view('equipos.create', [
            'categorias' => Categoria::orderBy('nombre')->get(),
            'categoriaActual' => $request->query('categoria'),
        ]);
    }

    /**
     * Crea un equipo.
     */
    public function store(Request $request): RedirectResponse
    {
        Equipo::create($request->validate($this->reglas()));

        return redirect()->route('equipos.index')->with('status', 'Equipo creado.');
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

        return view('equipos.show', ['equipo' => $equipo]);
    }

    /**
     * Muestra el formulario para editar un equipo.
     */
    public function edit(Equipo $equipo): View
    {
        return view('equipos.edit', [
            'equipo' => $equipo,
            'categorias' => Categoria::orderBy('nombre')->get(),
            'categoriaActual' => $equipo->categoria_id,
        ]);
    }

    /**
     * Actualiza un equipo. El estado de un equipo prestado no se cambia a mano (RF-17).
     */
    public function update(Request $request, Equipo $equipo): RedirectResponse
    {
        $reglas = $this->reglas($equipo);

        if ($equipo->estado === 'prestado') {
            unset($reglas['estado']);
        }

        $equipo->update($request->validate($reglas));

        return redirect()->route('equipos.index')->with('status', 'Equipo actualizado.');
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
     * Validaciones básicas del prototipo; se completan en el módulo Equipos.
     *
     * @return array<string, array<int, mixed>>
     */
    private function reglas(?Equipo $equipo = null): array
    {
        return [
            'categoria_id' => ['required', Rule::exists(Categoria::class, 'id')],
            'codigo_inventario' => ['required', 'string', 'max:50', Rule::unique(Equipo::class)->ignore($equipo)],
            'nombre' => ['required', 'string', 'max:255'],
            'marca' => ['nullable', 'string', 'max:255'],
            'modelo' => ['nullable', 'string', 'max:255'],
            'estado' => ['required', Rule::in(['disponible', 'mantenimiento'])],
            'solicitable_por' => ['required', Rule::in(['estudiante', 'docente', 'ambos'])],
            'descripcion' => ['nullable', 'string'],
        ];
    }
}
