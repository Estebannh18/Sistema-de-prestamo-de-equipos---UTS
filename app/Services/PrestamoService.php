<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Configuracion;
use App\Models\Equipo;
use App\Models\Horario;
use App\Models\Mantenimiento;
use App\Models\Prestamo;
use App\Models\User;
use App\Notifications\SolicitudCancelada;
use App\Notifications\SolicitudPorAprobar;
use App\Notifications\SolicitudRespondida;
use App\Support\Festivos;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de las solicitudes de préstamo:
 *
 * - Cada solicitud es de un solo espacio o equipo, para una fecha y un bloque de su horario.
 * - Si está libre, queda aprobada sola; menos los espacios que pide un estudiante, que van al funcionario.
 * - Si la persona ya llegó a su máximo de aprobados en curso, puede seguir, pero va al funcionario.
 * - Al aprobarse una, las pendientes del mismo espacio o equipo que se cruzan se rechazan solas.
 */
class PrestamoService
{
    /** Hasta cuántos días adelante se puede solicitar. */
    public const DIAS_VISIBLES = 14;

    public const MOTIVO_AUTOMATICO = 'Ya hay una solicitud aprobada para ese espacio o equipo en esa fecha y hora. Crea una nueva solicitud en los horarios disponibles.';

    /**
     * Fechas que se pueden elegir: desde hoy hasta DIAS_VISIBLES días adelante, sin domingos ni festivos.
     *
     * @return Collection<int, Carbon>
     */
    public function fechasSolicitables(): Collection
    {
        return collect(range(0, self::DIAS_VISIBLES))
            ->map(fn (int $dias) => today()->addDays($dias))
            ->reject(fn (Carbon $fecha) => $fecha->isSunday() || Festivos::esFestivo($fecha))
            ->values();
    }

    /**
     * Los bloques de un día para un espacio o equipo, con su situación para este usuario:
     * libre, ocupado (otra persona lo tiene aprobado), propio (ya lo pidió), pasado o anticipacion
     * (empieza muy pronto para pedirlo).
     *
     * @return Collection<int, array{horario: Horario, estado: string}>
     */
    public function bloquesDelDia(Equipo $equipo, Carbon $fecha, User $user): Collection
    {
        $horarios = $equipo->horarios()->where('dia_semana', Horario::diaDe($fecha))->get();
        $prestamos = Prestamo::where('equipo_id', $equipo->id)->whereDate('fecha', $fecha)->whereIn('estado', Prestamo::ACTIVOS)->get();
        $bloques = $this->conEstado($horarios, $prestamos, $fecha, $user, $equipo->esEspacio());

        // En mantenimiento ese día: los bloques se muestran, pero ninguno se puede pedir.
        return $equipo->mantenimientoEl($fecha)
            ? $bloques->map(fn (array $bloque) => ['horario' => $bloque['horario'], 'estado' => 'mantenimiento'])
            : $bloques;
    }

    /**
     * Categorías del tipo elegido que tienen algo que la persona puede pedir, con cuántas opciones hay en cada una.
     *
     * @return Collection<int, Categoria>
     */
    public function categoriasPara(User $user, bool $espacio): Collection
    {
        return Categoria::where('tipo', $espacio ? 'espacio' : 'equipo')
            ->withCount(['equipos as opciones_count' => fn ($query) => $query->disponiblesPara($user)])
            ->orderBy('nombre')->get()
            ->where('opciones_count', '>', 0)->values();
    }

    /**
     * Para el formulario de solicitud: los espacios o equipos que la persona puede pedir, del tipo elegido,
     * filtrados por categoría y por texto, con cuántos bloques libres tiene cada uno en la fecha.
     * Los que tienen bloques libres van primero. Los horarios y préstamos se consultan de una vez para todos.
     *
     * @return Collection<int, array{equipo: Equipo, libres: int, delDia: int}>
     */
    public function opciones(User $user, Carbon $fecha, bool $espacio, ?int $categoriaId = null, string $buscar = ''): Collection
    {
        $equipos = Equipo::with('categoria')->withCount('horarios')
            ->disponiblesPara($user)
            ->whereHas('categoria', fn ($query) => $query->where('tipo', $espacio ? 'espacio' : 'equipo'))
            ->when($categoriaId, fn ($query) => $query->where('categoria_id', $categoriaId))
            ->when($buscar !== '', fn ($query) => $query->where(function ($query) use ($buscar) {
                foreach (['nombre', 'codigo_inventario', 'marca', 'modelo', 'ubicacion', 'descripcion'] as $campo) {
                    $query->orWhere($campo, 'like', "%{$buscar}%");
                }
                $query->orWhereHas('categoria', fn ($query) => $query->where('nombre', 'like', "%{$buscar}%"));
            }))
            ->orderBy('nombre')->get();

        $ids = $equipos->modelKeys();
        $horarios = Horario::whereIn('equipo_id', $ids)->where('dia_semana', Horario::diaDe($fecha))->get()->groupBy('equipo_id');
        $prestamos = Prestamo::whereIn('equipo_id', $ids)->whereDate('fecha', $fecha)->whereIn('estado', Prestamo::ACTIVOS)->get()->groupBy('equipo_id');
        $mantenimientos = Mantenimiento::whereIn('equipo_id', $ids)->vigenteEl($fecha)->get()->keyBy('equipo_id');

        return $equipos->map(function (Equipo $equipo) use ($horarios, $prestamos, $mantenimientos, $fecha, $user, $espacio) {
            $bloques = $this->conEstado($horarios->get($equipo->id, collect()), $prestamos->get($equipo->id, collect()), $fecha, $user, $espacio);
            $mantenimiento = $mantenimientos->get($equipo->id);

            return [
                'equipo' => $equipo,
                'libres' => $mantenimiento ? 0 : $bloques->where('estado', 'libre')->count(),
                'delDia' => $bloques->count(),
                'mantenimiento' => $mantenimiento,
            ];
        })->sortByDesc(fn (array $opcion) => $opcion['libres'] > 0)->values();
    }

    /**
     * Situación de cada bloque de un día, según los préstamos activos de ese espacio o equipo en esa fecha.
     *
     * @return Collection<int, array{horario: Horario, estado: string}>
     */
    private function conEstado(Collection $horarios, Collection $prestamos, Carbon $fecha, User $user, bool $espacio): Collection
    {
        $limite = now()->addMinutes(Configuracion::minutosAnticipacion($user, $espacio));

        return $horarios->sortBy('hora_inicio')->values()->map(function (Horario $horario) use ($fecha, $prestamos, $user, $limite) {
            $inicio = $horario->inicioEn($fecha);
            $seCruzan = $prestamos->filter(fn (Prestamo $p) => $p->hora_inicio < $horario->hora_fin && $p->hora_fin > $horario->hora_inicio);

            $estado = match (true) {
                $seCruzan->contains(fn (Prestamo $p) => (int) $p->solicitante_id === (int) $user->id) => 'propio',
                $seCruzan->contains(fn (Prestamo $p) => in_array($p->estado, Prestamo::RESERVADOS, true)) => 'ocupado',
                $inicio->lessThanOrEqualTo(now()) => 'pasado',
                $inicio->lessThan($limite) => 'anticipacion',
                default => 'libre',
            };

            return ['horario' => $horario, 'estado' => $estado];
        });
    }

    /**
     * Antes de enviar: qué le pasaría a una solicitud nueva de este espacio o equipo.
     *
     * @return array{requiereAprobacion: bool, motivo: ?string, avisar: bool, maximo: int, enCurso: Collection<int, Prestamo>}
     */
    public function revisar(Equipo $equipo, User $user): array
    {
        $espacio = $equipo->esEspacio();
        $maximo = Configuracion::maximo($user, $espacio);
        $enCurso = $user->solicitudes()->enCurso()->deTipo($espacio)->with('equipo')->orderBy('fecha')->orderBy('hora_inicio')->get();
        $aprobadas = $enCurso->where('estado', 'aprobado')->count();

        if ($user->esEstudiante() && $espacio) {
            // Los espacios de los estudiantes siempre van al funcionario. Se avisa si ya tiene otros en curso.
            return ['requiereAprobacion' => true, 'motivo' => 'espacio', 'avisar' => $enCurso->count() >= $maximo, 'maximo' => $maximo, 'enCurso' => $enCurso];
        }

        $alMaximo = $aprobadas >= $maximo;

        return ['requiereAprobacion' => $alMaximo, 'motivo' => $alMaximo ? 'maximo' : null, 'avisar' => $alMaximo, 'maximo' => $maximo, 'enCurso' => $enCurso];
    }

    /**
     * Registra una solicitud. Queda aprobada sola o pendiente, según las reglas.
     * $confirmado: la persona vio el aviso de que ya tiene préstamos en curso y decidió seguir.
     *
     * @throws ValidationException con la explicación para la persona
     */
    public function solicitar(User $user, Equipo $equipo, Carbon $fecha, Horario $horario, bool $confirmado = false): Prestamo
    {
        return DB::transaction(function () use ($user, $equipo, $fecha, $horario, $confirmado) {
            // Bloquea el espacio o equipo: dos solicitudes al mismo tiempo se atienden una tras otra.
            $equipo = Equipo::whereKey($equipo->id)->lockForUpdate()->firstOrFail();
            $nombre = $equipo->esEspacio() ? 'el espacio' : 'el equipo';

            $this->exigir($equipo->permiteSolicitud($user), 'No puedes solicitar '.$nombre.' «'.$equipo->nombre.'».');
            $mantenimiento = $equipo->mantenimientoEl($fecha);
            $this->exigir(! $mantenimiento, ucfirst($nombre).' «'.$equipo->nombre.'» está en mantenimiento ese día. Motivo: '.$mantenimiento?->motivo);
            $this->exigir((int) $horario->equipo_id === (int) $equipo->id && $horario->dia_semana === Horario::diaDe($fecha), 'Ese bloque no está en el horario de ese día. Elige uno de la lista.');
            $this->exigir($fecha->lessThanOrEqualTo(today()->addDays(self::DIAS_VISIBLES)), 'Solo se puede solicitar hasta '.self::DIAS_VISIBLES.' días adelante.');
            $this->exigir(! Festivos::esFestivo($fecha), 'El '.$fecha->format('d/m/Y').' es festivo ('.Festivos::nombre($fecha).'): ese día no hay préstamos.');

            $inicio = $horario->inicioEn($fecha);
            $anticipacion = Configuracion::minutosAnticipacion($user, $equipo->esEspacio());
            $this->exigir($inicio->isFuture(), 'Ese bloque ya empezó o ya pasó. Elige otro.');
            $this->exigir($inicio->greaterThanOrEqualTo(now()->addMinutes($anticipacion)),
                ($equipo->esEspacio() ? 'Los espacios' : 'Los equipos').' se deben solicitar con al menos '.Configuracion::duracion($anticipacion).' de anticipación.');

            $seCruzan = Prestamo::queSeCruzan($equipo->id, $fecha->toDateString(), $horario->hora_inicio, $horario->hora_fin)->whereIn('estado', Prestamo::ACTIVOS);
            $this->exigir(! (clone $seCruzan)->whereIn('estado', Prestamo::RESERVADOS)->exists(), 'Ese bloque ya está ocupado. Elige otro horario.');
            $this->exigir(! (clone $seCruzan)->where('solicitante_id', $user->id)->exists(), 'Ya tienes una solicitud de «'.$equipo->nombre.'» en ese bloque.');

            $revision = $this->revisar($equipo, $user);
            $this->exigir(! $revision['avisar'] || $confirmado, 'Revisa el aviso y confirma si quieres continuar.');

            $prestamo = Prestamo::create([
                'equipo_id' => $equipo->id,
                'solicitante_id' => $user->id,
                'estado' => $revision['requiereAprobacion'] ? 'pendiente' : 'aprobado',
                'aprobacion' => $revision['requiereAprobacion'] ? null : 'automatica',
                'fecha' => $fecha->toDateString(),
                'hora_inicio' => $horario->hora_inicio,
                'hora_fin' => $horario->hora_fin,
                'fecha_esperada_devolucion' => $fecha->toDateString(),
                'fecha_respuesta' => $revision['requiereAprobacion'] ? null : now(),
            ]);

            // Si queda aprobada sola, las pendientes de otras personas para ese bloque no se rechazan:
            // el funcionario decide (por ejemplo, cancelar la de un docente para aprobar la de un estudiante).
            if ($prestamo->estado === 'pendiente') {
                Notification::send(User::where('rol', 'funcionario')->where('activo', true)->get(), new SolicitudPorAprobar($prestamo));
            }

            return $prestamo;
        });
    }

    /**
     * El funcionario o el administrador aprueba una solicitud pendiente.
     * Las demás pendientes del mismo espacio o equipo que se cruzan se rechazan solas.
     */
    public function aprobar(Prestamo $prestamo, User $atiende): void
    {
        DB::transaction(function () use ($prestamo, $atiende) {
            Equipo::whereKey($prestamo->equipo_id)->lockForUpdate()->first();
            $prestamo->refresh();

            $this->exigir($prestamo->estado === 'pendiente', 'Esta solicitud ya fue atendida.');
            $this->exigir((bool) $prestamo->inicio()?->isFuture(), 'Ya pasó la hora de inicio: esta solicitud no se puede aprobar.');
            $aprobada = Prestamo::queSeCruzan($prestamo->equipo_id, $prestamo->fecha->toDateString(), $prestamo->hora_inicio, $prestamo->hora_fin)
                ->where('estado', 'aprobado')->with('solicitante')->first();
            $this->exigir(! $aprobada, $aprobada
                ? "Primero debes cancelar la solicitud aprobada de {$aprobada->solicitante->name} ({$aprobada->solicitante->rol}) para el {$aprobada->fechaLarga()}, de {$aprobada->bloque()}."
                : '');

            $prestamo->update(['estado' => 'aprobado', 'aprobacion' => 'funcionario', 'funcionario_id' => $atiende->id, 'fecha_respuesta' => now()]);

            $this->quitarAvisos($prestamo);
            $prestamo->solicitante->notify(new SolicitudRespondida($prestamo));
            $this->rechazarLasQueSeCruzan($prestamo);
        });
    }

    /**
     * El funcionario o el administrador rechaza una solicitud pendiente, con un motivo.
     */
    public function rechazar(Prestamo $prestamo, User $atiende, string $motivo): void
    {
        DB::transaction(function () use ($prestamo, $atiende, $motivo) {
            $prestamo = Prestamo::whereKey($prestamo->id)->lockForUpdate()->firstOrFail();
            $this->exigir($prestamo->estado === 'pendiente', 'Esta solicitud ya fue atendida.');

            $prestamo->update(['estado' => 'rechazado', 'funcionario_id' => $atiende->id, 'fecha_respuesta' => now(), 'motivo_rechazo' => $motivo]);

            $this->quitarAvisos($prestamo);
            $prestamo->solicitante->notify(new SolicitudRespondida($prestamo));
        });
    }

    /**
     * El solicitante cancela su solicitud, pendiente o aprobada, hasta el tiempo configurado antes del inicio.
     */
    public function cancelar(Prestamo $prestamo, User $user): void
    {
        DB::transaction(function () use ($prestamo, $user) {
            $prestamo = Prestamo::whereKey($prestamo->id)->lockForUpdate()->firstOrFail();
            $this->exigir((int) $prestamo->solicitante_id === (int) $user->id, 'Solo quien hizo la solicitud la puede cancelar.');
            $this->exigir(in_array($prestamo->estado, Prestamo::CANCELABLES, true), 'Esta solicitud ya no se puede cancelar.');
            $this->exigir($prestamo->sePuedeCancelar(), 'Ya no se puede cancelar: se podía hasta '
                .Configuracion::duracion(Configuracion::minutosCancelacion($user)).' antes de la hora de inicio.');

            $prestamo->update(['estado' => 'cancelado', 'cancelado_en' => now(), 'cancelada_por' => 'solicitante', 'cancelado_por_id' => $user->id]);
            $this->quitarAvisos($prestamo);
        });
    }

    /**
     * Otras solicitudes activas del mismo espacio o equipo que se cruzan con esta, de la más antigua a la más reciente.
     *
     * @return Collection<int, Prestamo>
     */
    public function choques(Prestamo $prestamo): Collection
    {
        if (! $prestamo->fecha || ! $prestamo->hora_inicio) {
            return collect();
        }

        return Prestamo::queSeCruzan($prestamo->equipo_id, $prestamo->fecha->toDateString(), $prestamo->hora_inicio, $prestamo->hora_fin)
            ->whereKeyNot($prestamo->id)
            ->whereIn('estado', Prestamo::ACTIVOS)
            ->with('solicitante.programa')
            ->orderBy('created_at')->orderBy('id')
            ->get();
    }

    /**
     * Rechaza las pendientes del mismo espacio o equipo que se cruzan con una recién aprobada, y les avisa.
     */
    /**
     * El funcionario o el administrador cancela una solicitud aprobada que todavía no se ha entregado,
     * con su motivo. Al solicitante le llega el aviso. Así se libera el bloque para aprobar otra.
     */
    public function cancelarAprobada(Prestamo $prestamo, User $atiende, string $motivo): void
    {
        DB::transaction(function () use ($prestamo, $atiende, $motivo) {
            $prestamo = Prestamo::whereKey($prestamo->id)->lockForUpdate()->firstOrFail();
            $this->exigir($prestamo->estado === 'aprobado', 'Solo se puede cancelar una solicitud aprobada que todavía no se ha entregado.');
            $this->exigir((bool) $prestamo->fin()?->isFuture(), 'El bloque de esta solicitud ya terminó.');

            $prestamo->update(['estado' => 'cancelado', 'cancelado_en' => now(), 'cancelada_por' => 'personal', 'cancelado_por_id' => $atiende->id, 'motivo_cancelacion' => $motivo]);
            $prestamo->solicitante->notify(new SolicitudCancelada($prestamo));
        });
    }

    /**
     * Cancela solicitudes porque lo pedido ya no está disponible (mantenimiento o equipo dañado).
     * Cada solicitante recibe el aviso con el motivo, y se quitan los avisos de los funcionarios.
     * Se llama dentro de la transacción de quien la usa.
     *
     * @param  Collection<int, Prestamo>  $prestamos
     */
    public function cancelarPorElSistema(Collection $prestamos, string $motivo): void
    {
        $prestamos->each(function (Prestamo $prestamo) use ($motivo) {
            $prestamo->update(['estado' => 'cancelado', 'cancelado_en' => now(), 'cancelada_por' => 'sistema', 'cancelado_por_id' => null, 'motivo_cancelacion' => $motivo]);
            $this->quitarAvisos($prestamo);
            $prestamo->solicitante->notify(new SolicitudCancelada($prestamo));
        });
    }

    private function rechazarLasQueSeCruzan(Prestamo $aprobado): void
    {
        Prestamo::queSeCruzan($aprobado->equipo_id, $aprobado->fecha->toDateString(), $aprobado->hora_inicio, $aprobado->hora_fin)
            ->whereKeyNot($aprobado->id)
            ->where('estado', 'pendiente')
            ->get()
            ->each(function (Prestamo $otra) use ($aprobado) {
                $otra->update(['estado' => 'rechazado', 'funcionario_id' => $aprobado->funcionario_id, 'fecha_respuesta' => now(), 'motivo_rechazo' => self::MOTIVO_AUTOMATICO]);
                $this->quitarAvisos($otra);
                $otra->solicitante->notify(new SolicitudRespondida($otra));
            });
    }

    /**
     * Cuando una solicitud se atiende (o se cancela), su aviso desaparece de la campana de todos los funcionarios.
     */
    private function quitarAvisos(Prestamo $prestamo): void
    {
        DatabaseNotification::where('type', SolicitudPorAprobar::class)->where('data->prestamo_id', $prestamo->id)->delete();
    }

    private function exigir(bool $condicion, string $mensaje): void
    {
        if (! $condicion) {
            throw ValidationException::withMessages(['solicitud' => $mensaje]);
        }
    }
}
