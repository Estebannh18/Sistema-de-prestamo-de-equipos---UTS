<?php

namespace App\Http\Controllers;

use App\Http\Requests\UsuarioRequest;
use App\Models\Programa;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    /** Usuarios por página en el listado. */
    private const POR_PAGINA = 10;

    /**
     * Lista los usuarios, con búsqueda por nombre, correo o documento y filtros por rol, programa y estado.
     */
    public function index(Request $request): View
    {
        $buscar = trim((string) $request->query('buscar'));
        $rol = in_array($request->query('rol'), User::ROLES, true) ? $request->query('rol') : null;
        $estado = in_array($request->query('estado'), ['activos', 'inactivos'], true) ? $request->query('estado') : null;
        $programa = filter_var($request->query('programa'), FILTER_VALIDATE_INT) ?: null;

        $usuarios = User::query()
            ->with('programa')
            ->when($buscar !== '', fn ($query) => $query->where(function ($query) use ($buscar) {
                $query->where('name', 'like', "%{$buscar}%")
                    ->orWhere('email', 'like', "%{$buscar}%")
                    ->orWhere('documento', 'like', '%'.str_replace(['.', ' ', '-'], '', $buscar).'%');
            }))
            ->when($rol, fn ($query) => $query->where('rol', $rol))
            ->when($estado, fn ($query) => $query->where('activo', $estado === 'activos'))
            ->when($programa, fn ($query) => $query->where('programa_id', $programa))
            ->orderBy('name')
            ->paginate(self::POR_PAGINA)
            ->withQueryString();

        return view('usuarios.index', [
            'usuarios' => $usuarios,
            'roles' => User::ROLES,
            'buscar' => $buscar,
            'rolActual' => $rol,
            'estadoActual' => $estado,
            'programas' => Programa::agrupadosPorSede(),
            'programaActual' => $programa,
        ]);
    }

    /**
     * Muestra el formulario para crear un usuario.
     */
    public function create(): View
    {
        return view('usuarios.create', [
            'roles' => User::ROLES,
            'tiposDocumento' => User::TIPOS_DOCUMENTO,
            'programas' => Programa::agrupadosPorSede(),
        ]);
    }

    /**
     * Crea un usuario. El administrador crea las cuentas: no hay registro público.
     */
    public function store(UsuarioRequest $request): RedirectResponse
    {
        $usuario = User::create($request->validated());

        return redirect()->route('usuarios.index')->with('status', "Usuario {$usuario->name} creado.");
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
    public function edit(Request $request, User $usuario): View
    {
        return view('usuarios.edit', [
            'usuario' => $usuario,
            'roles' => User::ROLES,
            'tiposDocumento' => User::TIPOS_DOCUMENTO,
            'programas' => Programa::agrupadosPorSede(),
            'esPropio' => $usuario->is($request->user()),
            'prestamosActivos' => $usuario->solicitudes()->whereIn('estado', ['pendiente', 'aprobado'])->count(),
        ]);
    }

    /**
     * Actualiza un usuario. La contraseña solo cambia si se escribe una nueva.
     * Si cambian su contraseña o su rol, se cierran sus otras sesiones abiertas.
     */
    public function update(UsuarioRequest $request, User $usuario): RedirectResponse
    {
        $datos = $request->validated();

        if (empty($datos['password'])) {
            unset($datos['password']);
        }

        $cambioSensible = isset($datos['password']) || $datos['rol'] !== $usuario->rol;

        $usuario->update($datos);

        if ($cambioSensible && ! $usuario->is($request->user())) {
            $this->cerrarSesiones($usuario);
        }

        return redirect()->route('usuarios.index')->with('status', "Usuario {$usuario->name} actualizado.");
    }

    /**
     * Elimina un usuario, salvo que sea la propia cuenta, el último administrador
     * o alguien con préstamos asociados (RF-16).
     */
    public function destroy(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->is($request->user())) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        if ($usuario->esAdministrador() && $usuario->activo && User::administradoresActivos() === 1) {
            return back()->with('error', 'No se puede eliminar al único administrador activo.');
        }

        if ($usuario->solicitudes()->exists() || $usuario->prestamosAtendidos()->exists()) {
            return back()->with('error', "No se puede eliminar a {$usuario->name}: tiene préstamos en su historial. Si ya no debe entrar al sistema, desactívalo.");
        }

        $this->cerrarSesiones($usuario);
        $usuario->delete();

        return redirect()->route('usuarios.index')->with('status', "Usuario {$usuario->name} eliminado.");
    }

    /**
     * Desactiva una cuenta: no se borra nada, pero la persona ya no puede iniciar sesión.
     */
    public function desactivar(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->is($request->user())) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta.');
        }

        if (! $usuario->activo) {
            return back()->with('status', "{$usuario->name} ya estaba desactivado.");
        }

        if ($usuario->esAdministrador() && User::administradoresActivos() === 1) {
            return back()->with('error', 'No se puede desactivar al único administrador activo.');
        }

        if ($usuario->tienePrestamosActivos()) {
            return back()->with('error', "No se puede desactivar a {$usuario->name}: tiene préstamos pendientes o sin devolver.");
        }

        $usuario->forceFill(['activo' => false])->save();
        $this->cerrarSesiones($usuario);

        return back()->with('status', "{$usuario->name} quedó desactivado: ya no puede iniciar sesión.");
    }

    /**
     * Vuelve a activar una cuenta desactivada.
     */
    public function activar(User $usuario): RedirectResponse
    {
        $usuario->forceFill(['activo' => true])->save();

        return back()->with('status', "{$usuario->name} quedó activo: ya puede iniciar sesión.");
    }

    /**
     * Quita el 2FA de otro usuario, por ejemplo si perdió el celular.
     * Después entra solo con su contraseña y puede activarlo de nuevo desde su pestaña 2FA.
     */
    public function restablecerDosFactores(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->is($request->user())) {
            return back()->with('error', 'Para tu propia cuenta usa la pestaña 2FA.');
        }

        if (! $usuario->two_factor_secret) {
            return back()->with('status', "{$usuario->name} no tenía el 2FA activado.");
        }

        $usuario->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
        $this->cerrarSesiones($usuario);

        return back()->with('status', "Se restableció el 2FA de {$usuario->name}. Entrará solo con su contraseña y podrá activarlo de nuevo.");
    }

    /**
     * Cierra las sesiones abiertas de un usuario (las sesiones se guardan en la base de datos).
     */
    private function cerrarSesiones(User $usuario): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $usuario->id)->delete();
        }
    }
}
