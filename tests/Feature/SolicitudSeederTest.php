<?php

namespace Tests\Feature;

use App\Models\Prestamo;
use App\Models\User;
use App\Services\PrestamoService;
use App\Support\Festivos;
use Database\Seeders\DatabaseSeeder;
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

        // Estudiante de prueba: un equipo aprobado solo y un espacio pendiente.
        $mias = $estudiante->solicitudes()->with('equipo.categoria')->get();
        $this->assertCount(2, $mias);
        $equipo = $mias->first(fn ($p) => ! $p->equipo->esEspacio());
        $this->assertSame(['aprobado', 'automatica'], [$equipo->estado, $equipo->aprobacion]);
        $espacio = $mias->first(fn ($p) => $p->equipo->esEspacio());
        $this->assertSame('pendiente', $espacio->estado);

        // El segundo estudiante pidió el mismo espacio y bloque: el funcionario ve el choque.
        $this->assertSame(1, app(PrestamoService::class)->choques($espacio)->count());

        // También tiene una solicitud rechazada con motivo y una cancelada.
        $this->assertSame(['cancelado', 'pendiente', 'rechazado'], $segundo->solicitudes()->pluck('estado')->sort()->values()->all());
        $this->assertSame('Ese día el salón está reservado para una sustentación de grado.', Prestamo::where('estado', 'rechazado')->value('motivo_rechazo'));

        // Ninguna cae en domingo ni en festivo, y todas son desde mañana.
        Prestamo::all()->each(function (Prestamo $prestamo) {
            $this->assertFalse($prestamo->fecha->isSunday());
            $this->assertFalse(Festivos::esFestivo($prestamo->fecha));
            $this->assertTrue($prestamo->fecha->isAfter(today()));
        });

        // El funcionario tiene en la campana las dos pendientes; el aviso de la rechazada ya se quitó.
        $this->assertSame(2, $funcionario->unreadNotifications()->count());

        // Al volver a correrlo no se duplica nada.
        $this->seed(SolicitudSeeder::class);
        $this->assertSame(5, Prestamo::count());
    }
}
