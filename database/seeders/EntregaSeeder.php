<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use App\Models\Equipo;
use App\Models\Mantenimiento;
use App\Models\Prestamo;
use App\Models\User;
use App\Support\Festivos;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Entregas y devoluciones de ejemplo, del último día hábil (historia), para recorrer los módulos
 * Entregas y Devoluciones con los usuarios de prueba:
 * - devuelto a tiempo; devuelto con retraso y un pendiente; uno sin devolver (atrasado);
 * - un espacio con la devolución por corregir; uno no recogido; uno no entregado;
 * - una devolución rechazada, con su equipo fuera de servicio;
 * y un mantenimiento programado para la próxima semana.
 * Usa espacios y equipos distintos de los de SolicitudSeeder. Si ya hay entregas, no hace nada:
 *   php artisan db:seed --class=EntregaSeeder
 */
class EntregaSeeder extends Seeder
{
    private Carbon $dia;

    public function run(): void
    {
        $estudiante = User::where('email', 'estudiante@prestamos.test')->first();
        $segundo = User::where('email', 'estudiante2@prestamos.test')->first();
        $docente = User::where('email', 'docente@prestamos.test')->first();
        $funcionario = User::where('email', 'funcionario@prestamos.test')->first();

        if (! $estudiante || ! $segundo || ! $docente || ! $funcionario || Prestamo::has('entrega')->exists()) {
            return;
        }

        // Último día hábil antes de hoy (sin domingos ni festivos).
        $this->dia = today()->subDay();
        while ($this->dia->isSunday() || Festivos::esFestivo($this->dia)) {
            $this->dia->subDay();
        }

        // Espacios y equipos sin solicitudes todavía (los de SolicitudSeeder ya tienen).
        $libres = fn (string $tipo) => Equipo::disponiblesPara($estudiante)->whereHas('categoria', fn ($q) => $q->where('tipo', $tipo))
            ->doesntHave('prestamos')->has('horarios')->orderBy('id')->take(5)->get();
        $equipos = $libres('equipo');
        $espacios = $libres('espacio');
        if ($equipos->count() < 5 || $espacios->isEmpty()) {
            return;
        }

        // 1. Devuelto a tiempo.
        $p = $this->prestamo($equipos[0], $estudiante, '07:30', '09:00', 'devuelto');
        $this->entregar($p, $funcionario, 'Funda, cargador', null, 'bueno');
        $this->devolver($p, $funcionario, 'aceptada', 'Funda, cargador', minutos: 30);

        // 2. Devuelto con retraso y con un pendiente.
        $p = $this->prestamo($equipos[1], $docente, '09:00', '10:30', 'devuelto');
        $this->entregar($p, $funcionario, 'Tapa del lente, 2 patas, cable de poder, cable HDMI', 'Devolverlo con todos los cables.', 'con_observaciones', 'Tiene un pequeño rayón en la parte derecha.');
        $this->devolver($p, $funcionario, 'aceptada', 'Tapa del lente, 2 patas, cable de poder', minutos: 85, completos: false,
            faltantes: 'Falta el cable HDMI.', pendiente: 'Debe traer el cable HDMI que faltó.');

        // 3. Sin devolver: sigue entregado (devolución atrasada).
        $p = $this->prestamo($equipos[2], $segundo, '13:30', '15:00', 'entregado');
        $this->entregar($p, $funcionario, 'Cargador y mouse', null, 'bueno');

        // 4. Un espacio por corregir: lo dejaron sucio.
        $p = $this->prestamo($espacios[0], $segundo, '10:30', '12:00', 'por_corregir');
        $this->entregar($p, $funcionario, '40 sillas, control del aire, borrador', 'Entregar el salón barrido y el tablero limpio.', 'bueno');
        $this->devolver($p, $funcionario, 'por_corregir', '40 sillas, control del aire, borrador', minutos: 20, estado: 'sucio', cumplidas: false,
            motivo: 'El piso estaba sucio y el tablero sin limpiar.', plazo: '5 horas');

        // 5. No recogido: nadie llegó.
        $this->prestamo($equipos[3], $estudiante, '15:00', '16:30', 'no_recogido');

        // 6. No entregado: el préstamo anterior no se devolvió.
        $p = $this->prestamo($equipos[2], $docente, '15:00', '16:30', 'no_entregado');
        $p->update(['motivo_no_entrega' => 'El préstamo anterior no se devolvió a tiempo.', 'no_entregado_por_id' => $funcionario->id, 'no_entregado_en' => $this->hora('15:05')]);

        // 7. Devolución rechazada: llegó dañado y quedó fuera de servicio.
        $p = $this->prestamo($equipos[4], $estudiante, '18:30', '20:00', 'devolucion_rechazada');
        $this->entregar($p, $funcionario, 'Cargador', null, 'bueno');
        $this->devolver($p, $funcionario, 'rechazada', 'Cargador', minutos: 10, estado: 'danado', motivo: 'No enciende y tiene la pantalla rota.');
        $equipos[4]->mantenimientos()->create(['desde' => today(), 'hasta' => null, 'motivo' => 'Devolución rechazada: No enciende y tiene la pantalla rota.', 'creado_por_id' => $funcionario->id, 'prestamo_id' => $p->id]);

        // Un mantenimiento programado para la próxima semana, sin solicitudes en esas fechas.
        $proximo = today()->next(Carbon::MONDAY)->addWeek();
        $espacios->last()->mantenimientos()->create(['desde' => $proximo, 'hasta' => $proximo->copy()->addDays(2), 'motivo' => 'Pintura y arreglo de las luces.', 'creado_por_id' => $funcionario->id]);

        Mantenimiento::sincronizarEstados();
        $equipos->each->actualizarEstado();
        $espacios->each->actualizarEstado();
    }

    private function hora(string $hora): Carbon
    {
        return $this->dia->copy()->setTimeFromTimeString($hora);
    }

    private function prestamo(Equipo $equipo, User $user, string $inicio, string $fin, string $estado): Prestamo
    {
        return Prestamo::create([
            'equipo_id' => $equipo->id, 'solicitante_id' => $user->id, 'estado' => $estado,
            'aprobacion' => 'automatica', 'fecha_respuesta' => $this->dia->copy()->subDay(),
            'fecha' => $this->dia->toDateString(), 'fecha_esperada_devolucion' => $this->dia->toDateString(),
            'hora_inicio' => "{$inicio}:00", 'hora_fin' => "{$fin}:00",
        ]);
    }

    private function entregar(Prestamo $p, User $funcionario, string $complementos, ?string $condiciones, string $estado, ?string $observaciones = null): void
    {
        $p->entrega()->create([
            'funcionario_id' => $funcionario->id, 'entregado_en' => $this->hora(substr($p->hora_inicio, 0, 5))->subMinutes(10),
            'estado' => $estado, 'complementos' => $complementos, 'condiciones' => $condiciones, 'observaciones' => $observaciones,
        ]);
    }

    /** Revisión de la devolución, $minutos después del fin del bloque (más del tiempo configurado = con retraso). */
    private function devolver(Prestamo $p, User $funcionario, string $resultado, string $complementos, int $minutos, bool $completos = true,
        ?string $faltantes = null, ?string $pendiente = null, string $estado = 'bueno', ?bool $cumplidas = null, ?string $motivo = null, ?string $plazo = null): void
    {
        $p->devoluciones()->create([
            'funcionario_id' => $funcionario->id,
            'recibido_en' => $this->hora(substr($p->hora_fin, 0, 5))->addMinutes($minutos),
            'minutos_retraso' => max(0, $minutos - Configuracion::minutosDevolucion()),
            'estado' => $estado, 'complementos' => $complementos, 'complementos_completos' => $completos, 'faltantes' => $faltantes,
            'condiciones_cumplidas' => $p->entrega?->condiciones ? ($cumplidas ?? true) : null,
            'resultado' => $resultado, 'motivo' => $motivo, 'plazo' => $plazo, 'pendiente' => $pendiente,
        ]);
    }
}
