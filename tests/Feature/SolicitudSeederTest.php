<?php

namespace Tests\Feature;

use App\Models\Devolucion;
use App\Models\Mantenimiento;
use App\Models\Prestamo;
use App\Models\User;
use App\Services\PrestamoService;
use App\Support\Festivos;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\EntregaSeeder;
use Database\Seeders\HistorialSeeder;
use Database\Seeders\SolicitudSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Los datos de prueba traen solicitudes de ejemplo creadas con las reglas reales.
 */
class SolicitudSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_los_datos_de_prueba_traen_solicitudes_de_ejemplo(): void
    {
        // Martes 24 de noviembre de 2026, 8:00 a. m.
        $this->travelTo(Carbon::parse('2026-11-24 08:00:00'));
        $this->seed(DatabaseSeeder::class);

        $estudiante = User::where('email', 'estudiante@prestamos.test')->firstOrFail();
        $segundo = User::where('email', 'estudiante2@prestamos.test')->firstOrFail();
        $funcionario = User::where('email', 'funcionario@prestamos.test')->firstOrFail();

        // Estudiante: un equipo aprobado solo y un espacio pendiente (desde mañana; lo anterior es la historia de EntregaSeeder).
        $mias = $estudiante->solicitudes()->whereDate('fecha', '>', today())->with('equipo.categoria')->get();
        $this->assertCount(2, $mias);
        $equipo = $mias->first(fn ($p) => ! $p->equipo->esEspacio());
        $this->assertSame(['aprobado', 'automatica'], [$equipo->estado, $equipo->aprobacion]);
        $espacio = $mias->first(fn ($p) => $p->equipo->esEspacio());
        $this->assertSame('pendiente', $espacio->estado);

        // El segundo estudiante pidió el mismo espacio y bloque: el funcionario ve el choque.
        $this->assertSame(1, app(PrestamoService::class)->choques($espacio)->count());

        // Los ejemplos son los de los usuarios de prueba; HistorialSeeder agrega aparte el historial
        // de los usuarios de demostración (demo-…), que se revisa al final.
        $ejemplos = fn () => Prestamo::whereHas('solicitante', fn ($query) => $query->where('email', 'not like', 'demo-%'));

        // También tiene una solicitud rechazada con motivo y una cancelada.
        $this->assertSame(['cancelado', 'pendiente', 'rechazado'], $segundo->solicitudes()->whereDate('fecha', '>', today())->pluck('estado')->sort()->values()->all());
        $this->assertSame('Ese día el salón está reservado para una sustentación de grado.', $ejemplos()->where('estado', 'rechazado')->value('motivo_rechazo'));

        // Ninguna cae en domingo ni en festivo; las solicitudes nuevas son desde mañana y la historia, del último día hábil.
        $ejemplos()->get()->each(function (Prestamo $prestamo) {
            $this->assertFalse($prestamo->fecha->isSunday());
            $this->assertFalse(Festivos::esFestivo($prestamo->fecha));
            $this->assertTrue($prestamo->fecha->isAfter(today()) || $prestamo->fecha->toDateString() === '2026-11-23');
        });

        // El funcionario tiene en la campana las dos pendientes; el aviso de la rechazada ya se quitó.
        $this->assertSame(2, $funcionario->unreadNotifications()->count());

        // El docente tiene un espacio aprobado solo.
        $docente = User::where('email', 'docente@prestamos.test')->firstOrFail();
        $this->assertSame([['aprobado', 'automatica']], $docente->solicitudes()->whereDate('fecha', '>', today())->get()->map(fn ($p) => [$p->estado, $p->aprobacion])->all());

        // Entregas y devoluciones de ejemplo del último día hábil: el lunes 23/11.
        $historia = $ejemplos()->whereDate('fecha', '2026-11-23')->pluck('estado')->sort()->values()->all();
        $this->assertSame(['devolucion_rechazada', 'devuelto', 'devuelto', 'entregado', 'no_entregado', 'no_recogido', 'por_corregir'], $historia);
        $this->assertSame(1, Devolucion::whereNotNull('pendiente')->count());
        $this->assertSame(2, Mantenimiento::where('motivo', 'like', 'Devolución rechazada%')->orWhere('motivo', 'Pintura y arreglo de las luces.')->count());

        // Al volver a correrlos no se duplica nada.
        $total = Prestamo::count();
        $this->seed(SolicitudSeeder::class);
        $this->seed(EntregaSeeder::class);
        $this->seed(HistorialSeeder::class);
        $this->assertSame($total, Prestamo::count());

        // El historial de demostración para el reporte: varios días pasados, ninguno en domingo ni festivo.
        $historial = Prestamo::whereHas('solicitante', fn ($query) => $query->where('email', 'like', 'demo-%'))->get();
        $this->assertGreaterThan(50, $historial->count());
        $historial->each(function (Prestamo $prestamo) {
            $this->assertFalse($prestamo->fecha->isSunday());
            $this->assertFalse(Festivos::esFestivo($prestamo->fecha));
        });
    }
}
