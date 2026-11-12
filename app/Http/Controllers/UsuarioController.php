<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    /**
     * Lista los usuarios.
     */
    public function index(): View
    {
        return view('usuarios.index', ['usuarios' => User::orderBy('name')->get()]);
    }

    /**
     * Muestra el formulario para crear un usuario.
     */
    public function create(): View
    {
        return view('usuarios.create', ['roles' => User::ROLES]);
    }

    /**
     * Crea un usuario. El administrador crea las cuentas: no hay registro público.
     */
    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate($this->reglas() + [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        User::create($datos);

        return redirect()->route('usuarios.index')->with('status', 'Usuario creado.');
    }

    /**
     * Los datos de un usuario se consultan en su formulario de edición.
     */
    public function show(User $usuario): RedirectResponse
    {
        return redirect()->route('usuarios.edit', $usuario);
    }

    /**
     * Muestra el formulario para editar un usuario.
     */
    public function edit(User $usuario): View
    {
        return view('usuarios.edit', ['usuario' => $usuario, 'roles' => User::ROLES]);
    }

    /**
     * Actualiza un usuario. La contraseña solo cambia si se escribe una nueva.
     */
    public function update(Request $request, User $usuario): RedirectResponse
    {
        $datos = $request->validate($this->reglas($usuario) + [
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if (empty($datos['password'])) {
            unset($datos['password']);
        }

        $usuario->update($datos);

        return redirect()->route('usuarios.index')->with('status', 'Usuario actualizado.');
    }

    /**
     * Elimina un usuario, salvo que sea el propio o tenga préstamos asociados (RF-16).
     */
    public function destroy(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->is($request->user())) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        if ($usuario->solicitudes()->exists() || $usuario->prestamosAtendidos()->exists()) {
            return back()->with('error', 'No se puede eliminar un usuario con préstamos asociados.');
        }

        $usuario->delete();

        return redirect()->route('usuarios.index')->with('status', 'Usuario eliminado.');
    }

    /**
     * Validaciones mínimas del esqueleto; se completan en el módulo Usuarios.
     *
     * @return array<string, array<int, mixed>>
     */
    private function reglas(?User $usuario = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($usuario)],
            'documento' => ['required', 'string', 'max:30', Rule::unique(User::class)->ignore($usuario)],
            'rol' => ['required', Rule::in(User::ROLES)],
            'telefono' => ['nullable', 'string', 'max:30'],
            'programa_dependencia' => ['nullable', 'string', 'max:255'],
        ];
    }
}
