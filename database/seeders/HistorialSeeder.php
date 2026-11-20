<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use App\Models\Equipo;
use App\Models\Horario;
use App\Models\Prestamo;
use App\Models\Programa;
use App\Models\User;
use App\Support\Festivos;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * Historial de préstamos de prueba para el reporte: unos dos meses hacia atrás y diez días hacia adelante,
 * con el ciclo completo (pendiente, aprobado, entregado, devuelto, no recogido, por corregir,
 * rechazado y cancelado). Los entregados y devueltos traen su registro de entrega y de devolución.
 *
 * Usa estudiantes y docentes de demostración propios (correos demo-…@prestamos.test), así se puede
 * ejecutar sobre una base que ya tiene datos. Si ya existe su historial, no hace nada.
 * Los datos son siempre los mismos (semilla fija) para que la demostración sea repetible.
 */
class HistorialSeeder extends Seeder
{
    private const MOTIVOS_RECHAZO = [
        'Ese día el espacio está reservado para una sustentación de grado.',
        'El equipo tiene una revisión técnica programada.',
        'La solicitud no indica la actividad académica para la que se necesita.',
        'Ya se alcanzó el cupo de préstamos de la dependencia para esa jornada.',
    ];

    private const COMPLEMENTOS = ['Cargador', 'Cargador y funda', 'Control remoto y cable HDMI', 'Cable de poder', 'Llaves del salón', 'Sin complementos'];

    /** @var array<string, true> equipo|fecha|hora ocupados */
    private array $ocupados = [];

    private int $funcionario;

    private int $administrador;

    public function run(): void
    {
        $solicitantes = $this->solicitantes();

        if (Prestamo::whereIn('solicitante_id', $solicitantes->pluck('id'))->exists()) {
            return;
        }

        $funcionario = User::where('email', 'funcionario@prestamos.test')->value('id');
        $administrador = User::where('email', 'administrador@prestamos.test')->value('id');
        if (! $funcionario || ! $administrador) {
            return;
        }
        [$this->funcionario, $this->administrador] = [$funcionario, $administrador];

        mt_srand(20261119);

        $equipos = Equipo::with(['categoria', 'programas', 'horarios'])->where('estado', 'disponible')->get();
        $desde = today()->subDays(60);
        $hasta = today()->addDays(10);

        // Lo que ya está reservado en el periodo no se vuelve a ocupar.
        Prestamo::whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->get(['equipo_id', 'fecha', 'hora_inicio'])
            ->each(fn (Prestamo $prestamo) => $this->ocupados[$prestamo->equipo_id.'|'.$prestamo->fecha->toDateString().'|'.$prestamo->hora_inicio] = true);

        for ($dia = $desde->copy(); $dia->lte($hasta); $dia->addDay()) {
            // Como en la solicitud real: ni domingos ni festivos.
            if ($dia->isSunday() || Festivos::esFestivo($dia)) {
                continue;
            }

            // Más movimiento entre semana que los sábados.
            $cantidad = $dia->isSaturday() ? mt_rand(0, 2) : mt_rand(1, 4);
            for ($i = 0; $i < $cantidad; $i++) {
                $this->prestamo($dia, $solicitantes, $equipos);
            }
        }

        $this->asegurarAtrasados($solicitantes);
    }

    /**
     * La demostración necesita préstamos con devolución atrasada. Si el sorteo no dejó ninguno,
     * los dos devueltos más recientes de los últimos días pasan a seguir entregados (sin devolución).
     */
    private function asegurarAtrasados(Collection $solicitantes): void
    {
        $delHistorial = Prestamo::whereIn('solicitante_id', $solicitantes->pluck('id'));

        if ($delHistorial->clone()->where('estado', 'entregado')->get()->contains(fn (Prestamo $prestamo) => $prestamo->estaAtrasado())) {
            return;
        }

        $delHistorial->clone()->where('estado', 'devuelto')->whereDate('fecha', '>=', today()->subDays(4))->whereDate('fecha', '<', today())
            ->orderByDesc('fecha')->orderByDesc('hora_inicio')->take(2)->get()
            ->each(function (Prestamo $prestamo) {
                $prestamo->devoluciones()->delete();
                $prestamo->update(['estado' => 'entregado']);
            });
    }

    /**
     * Estudiantes y docentes de demostración, de varias carreras.
     *
     * @return Collection<int, User>
     */
    private function solicitantes(): Collection
    {
        $personas = [
            ['Laura Méndez Ortiz', 'estudiante', 'Ingeniería de Sistemas'],
            ['Andrés Felipe Rojas', 'estudiante', 'Ingeniería Ambiental'],
            ['Valentina Cáceres', 'estudiante', 'Ingeniería Electrónica'],
            ['Santiago Villamizar', 'estudiante', 'Ingeniería Civil'],
            ['María José Pinzón', 'estudiante', 'Contaduría Pública'],
            ['Camilo Andrés Duarte', 'estudiante', 'Tecnología en Desarrollo de Sistemas Informáticos'],
            ['Daniela Suárez Gómez', 'estudiante', 'Profesional en Diseño de Modas'],
            ['Juan David Arenas', 'estudiante', 'Ingeniería Industrial'],
            ['Paola Andrea Niño', 'docente', 'Ingeniería de Sistemas'],
            ['Hernán Darío Quintero', 'docente', 'Ingeniería Eléctrica'],
            ['Claudia Patricia Rueda', 'docente', 'Administración de Empresas'],
            ['Óscar Iván Mantilla', 'docente', 'Ingeniería Ambiental'],
        ];

        return collect($personas)->map(function (array $persona, int $i) {
            [$nombre, $rol, $carrera] = $persona;

            $usuario = User::firstOrCreate(
                ['email' => 'demo-'.($rol === 'docente' ? 'docente' : 'estudiante').($i + 1).'@prestamos.test'],
                [
                    'name' => $nombre,
                    'password' => Hash::make('password'),
                    'rol' => $rol,
                    'tipo_documento' => $rol === 'docente' ? 'CC' : 'TI',
                    'documento' => (string) (1090000100 + $i),
                    'programa_id' => Programa::where('nombre', $carrera)->orderByRaw("sede = 'Bucaramanga' desc")->value('id'),
                ],
            );

            // Como los de UsuarioSeeder: con el correo verificado, para que puedan entrar a la demostración.
            if (! $usuario->email_verified_at) {
                $usuario->forceFill(['email_verified_at' => now()])->save();
            }

            return $usuario;
        });
    }

    /**
     * Crea un préstamo para el día dado, si encuentra un equipo y un bloque libres.
     */
    private function prestamo(Carbon $dia, Collection $solicitantes, Collection $equipos): void
    {
        $solicitante = $solicitantes[mt_rand(0, $solicitantes->count() - 1)];
        $opciones = $equipos->filter(fn (Equipo $equipo) => $equipo->permiteSolicitud($solicitante)
            && $equipo->horarios->contains('dia_semana', $dia->dayOfWeekIso))->values();

        if ($opciones->isEmpty()) {
            return;
        }

        for ($intento = 0; $intento < 5; $intento++) {
            $equipo = $opciones[mt_rand(0, $opciones->count() - 1)];
            $bloques = $equipo->horarios->where('dia_semana', $dia->dayOfWeekIso)->values();
            $bloque = $bloques[mt_rand(0, $bloques->count() - 1)];
            $clave = $equipo->id.'|'.$dia->toDateString().'|'.$bloque->hora_inicio;

            if (! isset($this->ocupados[$clave])) {
                $this->ocupados[$clave] = true;
                $this->crear($dia, $solicitante, $equipo, $bloque);

                return;
            }
        }
    }

    private function crear(Carbon $dia, User $solicitante, Equipo $equipo, Horario $bloque): void
    {
        $inicio = Carbon::parse($dia->toDateString().' '.$bloque->hora_inicio);
        $fin = Carbon::parse($dia->toDateString().' '.$bloque->hora_fin);
        $estado = $this->estadoSegunLaHora($inicio, $fin);

        $creado = $inicio->copy()->subDays(mt_rand(1, 5))->setTime(mt_rand(7, 17), mt_rand(0, 59));
        if ($creado->isFuture()) {
            $creado = now()->subHours(mt_rand(1, 48));
        }

        // Los equipos pueden aprobarse solos; los espacios que pide un estudiante los aprueba un funcionario.
        $paso = ! in_array($estado, ['pendiente', 'rechazado', 'cancelado'], true);
        $automatica = $paso && ! ($equipo->esEspacio() && $solicitante->esEstudiante()) && mt_rand(1, 100) <= 70;
        $atendio = ($paso && ! $automatica) || $estado === 'rechazado' ? $this->quienAtiende() : null;
        $respuesta = $atendio ? $creado->copy()->addMinutes(mt_rand(5, 600))->min(now()) : null;

        $prestamo = Prestamo::forceCreate([
            'equipo_id' => $equipo->id,
            'solicitante_id' => $solicitante->id,
            'funcionario_id' => $atendio,
            'estado' => $estado,
            'fecha' => $dia->toDateString(),
            'hora_inicio' => $bloque->hora_inicio,
            'hora_fin' => $bloque->hora_fin,
            'aprobacion' => $paso ? ($automatica ? 'automatica' : 'funcionario') : null,
            'fecha_esperada_devolucion' => $dia->toDateString(),
            'fecha_respuesta' => $respuesta,
            'cancelado_en' => $estado === 'cancelado' ? $creado->copy()->addHours(mt_rand(1, 20))->min(now()) : null,
            'cancelada_por' => $estado === 'cancelado' ? 'solicitante' : null,
            'cancelado_por_id' => $estado === 'cancelado' ? $solicitante->id : null,
            'motivo_rechazo' => $estado === 'rechazado' ? self::MOTIVOS_RECHAZO[mt_rand(0, count(self::MOTIVOS_RECHAZO) - 1)] : null,
            'created_at' => $creado,
            'updated_at' => $respuesta ?? $creado,
        ]);

        // Entrega: unos minutos antes del inicio del bloque.
        if (in_array($estado, ['entregado', 'devuelto', 'por_corregir'], true)) {
            $complementos = self::COMPLEMENTOS[mt_rand(0, count(self::COMPLEMENTOS) - 1)];
            $prestamo->entrega()->create([
                'funcionario_id' => $this->quienAtiende(),
                'entregado_en' => $inicio->copy()->subMinutes(mt_rand(0, 15)),
                'estado' => mt_rand(1, 100) <= 85 ? 'bueno' : 'con_observaciones',
                'complementos' => $complementos,
            ]);

            // Devolución: casi siempre a tiempo; algunas con retraso y unas pocas por corregir.
            if ($estado !== 'entregado') {
                $minutos = mt_rand(1, 100) <= 85 ? mt_rand(-10, 15) : mt_rand(40, 120);
                $porCorregir = $estado === 'por_corregir';
                $prestamo->devoluciones()->create([
                    'funcionario_id' => $this->quienAtiende(),
                    'recibido_en' => $fin->copy()->addMinutes($minutos),
                    'minutos_retraso' => max(0, $minutos - Configuracion::minutosDevolucion()),
                    'estado' => $porCorregir ? 'sucio' : 'bueno',
                    'complementos' => $complementos,
                    'complementos_completos' => true,
                    'resultado' => $porCorregir ? 'por_corregir' : 'aceptada',
                    'motivo' => $porCorregir ? 'Se devolvió sucio: debe limpiarse antes de recibirlo.' : null,
                    'plazo' => $porCorregir ? 'Hasta el siguiente día hábil' : null,
                ]);
            }
        }
    }

    /**
     * Estado según el momento del bloque:
     * - ya terminó: devuelto en su mayoría, algunos no recogidos, rechazados, cancelados o por corregir,
     *   y unos pocos de los últimos días todavía sin devolver (devolución atrasada);
     * - en curso: entregado;
     * - por venir: pendiente, aprobado o cancelado.
     */
    private function estadoSegunLaHora(Carbon $inicio, Carbon $fin): string
    {
        if ($fin->isPast()) {
            $reciente = $fin->greaterThan(now()->subDays(3));

            return $this->sortear([
                'devuelto' => 64, 'no_recogido' => 7, 'rechazado' => 12, 'cancelado' => 12, 'por_corregir' => 3,
                'entregado' => $reciente ? 60 : 0,
            ]);
        }

        if ($inicio->isPast()) {
            return 'entregado';
        }

        return $this->sortear(['pendiente' => 35, 'aprobado' => 55, 'cancelado' => 10]);
    }

    /** El funcionario atiende la mayoría; el administrador, algunas. */
    private function quienAtiende(): int
    {
        return mt_rand(1, 100) <= 75 ? $this->funcionario : $this->administrador;
    }

    /** Elige una clave según su peso. */
    private function sortear(array $pesos): string
    {
        $tiro = mt_rand(1, array_sum($pesos));
        foreach ($pesos as $valor => $peso) {
            if (($tiro -= $peso) <= 0) {
                return $valor;
            }
        }

        return array_key_first($pesos);
    }
}
