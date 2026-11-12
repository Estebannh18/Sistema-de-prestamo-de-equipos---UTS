<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoriaController extends Controller
{
    /**
     * Lista las categorías y las filtra por nombre.
     */
    public function index(Request $request): View
    {
        $buscar = trim((string) $request->query('buscar'));

        $categorias = Categoria::withCount('equipos')
            ->when($buscar !== '', fn ($query) => $query->where('nombre', 'like', "%{$buscar}%"))
            ->orderBy('nombre')
            ->get();

        return view('categorias.index', ['categorias' => $categorias, 'buscar' => $buscar]);
    }

    /**
     * Muestra el formulario para crear una categoría.
     */
    public function create(): View
    {
        return view('categorias.create');
    }

    /**
     * Crea una categoría.
     */
    public function store(Request $request): RedirectResponse
    {
        Categoria::create($request->validate($this->reglas()));

        return redirect()->route('categorias.index')->with('status', 'Categoría creada.');
    }

    /**
     * Los equipos de una categoría se consultan en el listado de equipos.
     */
    public function show(Categoria $categoria): RedirectResponse
    {
        return redirect()->route('equipos.index', ['categoria' => $categoria->id]);
    }

    /**
     * Muestra el formulario para editar una categoría.
     */
    public function edit(Categoria $categoria): View
    {
        return view('categorias.edit', ['categoria' => $categoria]);
    }

    /**
     * Actualiza una categoría.
     */
    public function update(Request $request, Categoria $categoria): RedirectResponse
    {
        $categoria->update($request->validate($this->reglas($categoria)));

        return redirect()->route('categorias.index')->with('status', 'Categoría actualizada.');
    }

    /**
     * Elimina una categoría, salvo que tenga equipos (RF-16).
     */
    public function destroy(Categoria $categoria): RedirectResponse
    {
        if ($categoria->equipos()->exists()) {
            return back()->with('error', 'No se puede eliminar una categoría que tiene equipos.');
        }

        $categoria->delete();

        return redirect()->route('categorias.index')->with('status', 'Categoría eliminada.');
    }

    /**
     * Validaciones básicas del prototipo; se completan en el módulo Categorías.
     *
     * @return array<string, array<int, mixed>>
     */
    private function reglas(?Categoria $categoria = null): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255', Rule::unique(Categoria::class)->ignore($categoria)],
            'descripcion' => ['nullable', 'string'],
        ];
    }
}
