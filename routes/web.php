<?php

use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\EquipoController;
use App\Http\Controllers\NoticiaController;
use App\Http\Controllers\PrestamoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\SeguridadController;
use App\Http\Controllers\UsuarioController;
use App\Models\Noticia;
use Illuminate\Support\Facades\Route;

// Portada provisional: el proyecto aún está en construcción.
Route::view('/', 'inicio')->name('inicio');

// Inicio de cada usuario: sus módulos y las 3 noticias más recientes.
Route::get('/dashboard', function () {
    return view('dashboard', ['noticias' => Noticia::query()->recientes()->take(3)->get()]);
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
    });

    Route::middleware('rol:estudiante,docente')->group(function () {
        Route::resource('prestamos', PrestamoController::class)->only(['create', 'store']);
    });

    Route::middleware('rol:administrador,funcionario')->group(function () {
        Route::patch('prestamos/{prestamo}/aprobar', [PrestamoController::class, 'aprobar'])->name('prestamos.aprobar');
        Route::patch('prestamos/{prestamo}/rechazar', [PrestamoController::class, 'rechazar'])->name('prestamos.rechazar');
        Route::patch('prestamos/{prestamo}/devolver', [PrestamoController::class, 'devolver'])->name('prestamos.devolver');
        Route::get('reportes/prestamos', [ReporteController::class, 'index'])->name('reportes.prestamos');
        Route::get('reportes/prestamos/pdf', [ReporteController::class, 'pdf'])->name('reportes.prestamos.pdf');
        // Crear, editar y borrar noticias. Qué noticia puede editar cada uno lo decide NoticiaPolicy.
        Route::resource('noticias', NoticiaController::class)->except(['index', 'show']);
    });

    Route::resource('equipos', EquipoController::class)->only(['index', 'show']);
    Route::resource('prestamos', PrestamoController::class)->only(['index', 'show']);
    // Las noticias y sus archivos los ven todos los usuarios con sesión iniciada.
    Route::get('noticias/adjuntos/{adjunto}', [NoticiaController::class, 'adjunto'])->name('noticias.adjunto');
    Route::resource('noticias', NoticiaController::class)->only(['index', 'show']);
});

require __DIR__.'/auth.php';
