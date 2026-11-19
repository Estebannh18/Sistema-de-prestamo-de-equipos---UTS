<?php

namespace Database\Seeders;

use App\Models\Equipo;
use App\Models\Horario;
use App\Models\Prestamo;
use App\Models\User;
use App\Services\PrestamoService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Solicitudes de ejemplo para recorrer el sistema con los usuarios de prueba.
 * Se crean con PrestamoService, así que siguen las mismas reglas que una solicitud real
 * (horarios, anticipación, festivos, carreras, máximos y avisos a los funcionarios):
 *
 * - Estudiante de prueba: un equipo aprobado solo y un espacio pendiente de aprobación.
 * - Segundo estudiante: el mismo espacio y bloque (el funcionario verá el choque), otro espacio
 *   que el funcionario rechaza con su motivo y un equipo que el estudiante cancela.
 *
 * Las fechas se calculan desde hoy. Si ya hay préstamos, no hace nada:
 *   php artisan db:seed --class=SolicitudSeeder
 */
class SolicitudSeeder extends Seeder
{
    public function __construct(private PrestamoService $prestamos) {}

    public function run(): void
    {
        $estudiante = User::where('email', 'estudiante@prestamos.test')->first();
        $segundo = User::where('email', 'estudiante2@prestamos.test')->first();
        $funcionario = User::where('email', 'funcionario@prestamos.test')->first();

        if (! $estudiante || ! $segundo || ! $funcionario || Prestamo::exists()) {
            return;
        }

        [$equipo, $otroEquipo] = $this->opciones($estudiante, 'equipo');
        [$espacio, $otroEspacio] = $this->opciones($estudiante, 'espacio');

        // 1. Equipo de un estudiante, libre y sin pasar su máximo: queda aprobado solo.
        $this->pedir($estudiante, $equipo);

        // 2. Espacio de un estudiante: siempre pasa a aprobación y el funcionario recibe el aviso.
        $pendiente = $this->pedir($estudiante, $espacio);

        // 3. Otro estudiante pide el mismo espacio y bloque: el funcionario verá las dos solicitudes.
        if ($pendiente) {
            $horario = Horario::where('equipo_id', $espacio->id)
                ->where('dia_semana', Horario::diaDe($pendiente->fecha))
                ->where('hora_inicio', $pendiente->hora_inicio)
                ->first();
            $this->prestamos->solicitar($segundo, $espacio, $pendiente->fecha->copy(), $horario);
        }

        // 4. Otro espacio que el funcionario rechaza con su motivo.
        if ($rechazada = $this->pedir($segundo, $otroEspacio, saltarDias: 1, confirmado: true)) {
            $this->prestamos->rechazar($rechazada, $funcionario, 'Ese día el salón está reservado para una sustentación de grado.');
        }

        // 5. Un equipo aprobado que el estudiante cancela a tiempo.
        if ($cancelada = $this->pedir($segundo, $otroEquipo, saltarDias: 2)) {
            $this->prestamos->cancelar($cancelada, $segundo);
        }
    }

    /**
     * Los dos primeros espacios o equipos (con horario) que el estudiante puede pedir.
     *
     * @return array{0: ?Equipo, 1: ?Equipo}
     */
    private function opciones(User $user, string $tipo): array
    {
        $lista = Equipo::disponiblesPara($user)
            ->whereHas('categoria', fn ($query) => $query->where('tipo', $tipo))
            ->has('horarios')
            ->orderBy('id')
            ->take(2)
            ->get();

        return [$lista->get(0), $lista->get(1)];
    }

    /**
     * Pide el primer bloque libre desde mañana (saltando días si se indica). Devuelve null si no hay.
     */
    private function pedir(User $user, ?Equipo $equipo, int $saltarDias = 0, bool $confirmado = false): ?Prestamo
    {
        if (! $equipo) {
            return null;
        }

        $fechas = $this->prestamos->fechasSolicitables()->filter(fn (Carbon $fecha) => $fecha->isAfter(today()))->slice($saltarDias);

        foreach ($fechas as $fecha) {
            $libre = $this->prestamos->bloquesDelDia($equipo, $fecha, $user)->firstWhere('estado', 'libre');

            if ($libre) {
                return $this->prestamos->solicitar($user, $equipo, $fecha, $libre['horario'], $confirmado);
            }
        }

        return null;
    }
}
