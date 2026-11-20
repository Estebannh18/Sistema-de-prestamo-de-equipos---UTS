<?php

use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\DevolucionController;
use App\Http\Controllers\EntregaController;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\HorarioController;
use App\Http\Controllers\MantenimientoController;
use App\Http\Controllers\NoticiaController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\PrestamoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\SeguridadController;
use App\Http\Controllers\UsuarioController;
use App\Models\Noticia;
use App\Models\Prestamo;
use Illuminate\Support\Facades\Route;

// Portada pública del sistema.
Route::view('/', 'inicio')->name('inicio');

// Inicio de cada usuario: sus módulos, las 3 noticias más recientes y, para el personal, lo pendiente de hoy.
Route::get('/dashboard', function () {
    $usuario = auth()->user();
    $paraHoy = $usuario->esAdministrador() || $usuario->esFuncionario() ? [
        'Por aprobar' => [Prestamo::where('estado', 'pendiente')->count(), 'prestamos.pendientes', [], 'reloj'],
        'Por entregar hoy' => [Prestamo::where('estado', 'aprobado')->whereDate('fecha', today())->count(), 'entregas.index', [], 'llave'],
        'Por recibir' => [Prestamo::where('estado', 'entregado')->count(), 'devoluciones.index', [], 'devolucion'],
        'Por corregir' => [Prestamo::where('estado', 'por_corregir')->count(), 'devoluciones.index', ['ver' => 'por_corregir'], 'alerta'],
    ] : [];

    return view('dashboard', ['noticias' => Noticia::query()->recientes()->take(3)->get(), 'paraHoy' => $paraHoy]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Pestaña 2FA: opcional para los cuatro roles (RF-18)
    Route::get('/seguridad/2fa', [SeguridadController::class, 'show'])->name('seguridad.2fa');

    // Rutas de la Tabla 11 del documento 9.
    // Las rutas fijas, como /equipos/create, van antes que las que tienen parámetro, como /equipos/{equipo}.
    Route::middleware('rol:administrador')->group(function () {
        Route::resource('usuarios', UsuarioController::class);
        Route::patch('usuarios/{usuario}/desactivar', [UsuarioController::class, 'desactivar'])->name('usuarios.desactivar');
        Route::patch('usuarios/{usuario}/activar', [UsuarioController::class, 'activar'])->name('usuarios.activar');
        Route::patch('usuarios/{usuario}/restablecer-2fa', [UsuarioController::class, 'restablecerDosFactores'])->name('usuarios.restablecer2fa');
        Route::resource('categorias', CategoriaController::class);
        Route::resource('equipos', EquipoController::class)->except(['index', 'show']);
        // Horarios de cada espacio o equipo: bloques de horas por día, de lunes a sábado.
        Route::get('equipos/{equipo}/horarios', [HorarioController::class, 'edit'])->name('horarios.edit');
        Route::put('equipos/{equipo}/horarios', [HorarioController::class, 'update'])->name('horarios.update');
        // Módulo Configuración: máximos, tiempo para cancelar y anticipación mínima.
        Route::get('configuracion', [ConfiguracionController::class, 'edit'])->name('configuracion.edit');
        Route::put('configuracion', [ConfiguracionController::class, 'update'])->name('configuracion.update');
    });

    Route::middleware('rol:estudiante,docente')->group(function () {
        Route::resource('prestamos', PrestamoController::class)->only(['create', 'store']);
        Route::patch('prestamos/{prestamo}/cancelar', [PrestamoController::class, 'cancelar'])->name('prestamos.cancelar');
    });

    Route::middleware('rol:administrador,funcionario')->group(function () {
        // Solicitudes por aprobar: los funcionarios reciben aviso; el administrador las ve y las puede atender.
        Route::get('prestamos/pendientes', [PrestamoController::class, 'pendientes'])->name('prestamos.pendientes');
        Route::patch('prestamos/{prestamo}/aprobar', [PrestamoController::class, 'aprobar'])->name('prestamos.aprobar');
        Route::patch('prestamos/{prestamo}/rechazar', [PrestamoController::class, 'rechazar'])->name('prestamos.rechazar');
        Route::patch('prestamos/{prestamo}/cancelar-aprobada', [PrestamoController::class, 'cancelarAprobada'])->name('prestamos.cancelar-aprobada');
        Route::get('equipos/{equipo}/mantenimientos/create', [MantenimientoController::class, 'create'])->name('mantenimientos.create');
        Route::post('equipos/{equipo}/mantenimientos', [MantenimientoController::class, 'store'])->name('mantenimientos.store');
        Route::patch('mantenimientos/{mantenimiento}/terminar', [MantenimientoController::class, 'terminar'])->name('mantenimientos.terminar');
        Route::get('entregas', [EntregaController::class, 'index'])->name('entregas.index');
        Route::get('prestamos/{prestamo}/entrega', [EntregaController::class, 'create'])->name('entregas.create');
        Route::post('prestamos/{prestamo}/entrega', [EntregaController::class, 'store'])->name('entregas.store');
        Route::patch('prestamos/{prestamo}/no-entregado', [EntregaController::class, 'noEntregado'])->name('entregas.no-entregado');
        Route::get('devoluciones', [DevolucionController::class, 'index'])->name('devoluciones.index');
        Route::get('prestamos/{prestamo}/devolucion', [DevolucionController::class, 'create'])->name('devoluciones.create');
        Route::post('prestamos/{prestamo}/devolucion', [DevolucionController::class, 'store'])->name('devoluciones.store');
        Route::patch('devoluciones/{devolucion}/resolver', [DevolucionController::class, 'resolverPendiente'])->name('devoluciones.resolver');
        Route::get('reportes/prestamos', [ReporteController::class, 'index'])->name('reportes.prestamos');
        Route::get('reportes/prestamos/pdf', [ReporteController::class, 'pdf'])->name('reportes.prestamos.pdf');
        // Crear, editar y borrar noticias. Qué noticia puede editar cada uno lo decide NoticiaPolicy.
        Route::resource('noticias', NoticiaController::class)->except(['index', 'show']);
    });

    Route::resource('equipos', EquipoController::class)->only(['index', 'show']);
    Route::resource('prestamos', PrestamoController::class)->only(['index', 'show']);
    // Fotos de entregas y devoluciones: solo el personal y el solicitante, con la sesión iniciada.
    Route::get('prestamos/fotos/{foto}', [PrestamoController::class, 'foto'])->name('prestamos.foto');

    // Notificaciones dentro de la app (campana y bandeja).
    Route::get('notificaciones', [NotificacionController::class, 'index'])->name('notificaciones.index');
    Route::get('notificaciones/resumen', [NotificacionController::class, 'resumen'])->name('notificaciones.resumen');
    Route::post('notificaciones/leer-todas', [NotificacionController::class, 'leerTodas'])->name('notificaciones.leer-todas');
    Route::post('notificaciones/{id}/abrir', [NotificacionController::class, 'abrir'])->name('notificaciones.abrir');
    // Las noticias y sus archivos los ven todos los usuarios con sesión iniciada.
    Route::get('noticias/adjuntos/{adjunto}', [NoticiaController::class, 'adjunto'])->name('noticias.adjunto');
    Route::resource('noticias', NoticiaController::class)->only(['index', 'show']);
});

require __DIR__.'/auth.php';
