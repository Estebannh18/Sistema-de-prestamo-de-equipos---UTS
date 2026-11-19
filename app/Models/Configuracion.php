<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Valores del módulo Configuración que cambia el administrador: máximos de préstamos aprobados,
 * tiempo para cancelar y anticipación mínima para solicitar, por rol.
 * Los tiempos se guardan en minutos.
 */
class Configuracion extends Model
{
    protected $table = 'configuraciones';

    protected $fillable = ['clave', 'valor'];

    /** Grupos en el orden en que se muestran: clave => [título, explicación]. */
    public const GRUPOS = [
        'maximos' => ['Máximo de préstamos aprobados', 'Cuántos préstamos aprobados en curso puede tener cada persona. Si ya llegó al máximo y pide otro, el sistema le avisa y la solicitud pasa a aprobación del funcionario.'],
        'cancelacion' => ['Tiempo mínimo para cancelar', 'Hasta cuánto antes de la hora de inicio se puede cancelar una solicitud. Por ejemplo, con 90 minutos, un préstamo de las 9:00 se cancela hasta las 7:30.'],
        'anticipacion' => ['Anticipación mínima para solicitar', 'Con cuánto tiempo antes de la hora de inicio se debe hacer la solicitud.'],
    ];

    /** clave => [grupo, etiqueta, unidad (cantidad o minutos), valor por defecto, mínimo, máximo]. */
    public const VALORES = [
        'max_equipos_estudiante' => ['maximos', 'Equipos por estudiante', 'cantidad', 1, 1, 20],
        'max_espacios_estudiante' => ['maximos', 'Espacios por estudiante', 'cantidad', 1, 1, 20],
        'max_equipos_docente' => ['maximos', 'Equipos por docente', 'cantidad', 2, 1, 20],
        'max_espacios_docente' => ['maximos', 'Espacios por docente', 'cantidad', 2, 1, 20],
        'cancelacion_estudiante' => ['cancelacion', 'Estudiantes', 'minutos', 90, 0, 10080],
        'cancelacion_docente' => ['cancelacion', 'Docentes', 'minutos', 90, 0, 10080],
        'anticipacion_equipos_estudiante' => ['anticipacion', 'Equipos, para estudiantes', 'minutos', 120, 0, 20160],
        'anticipacion_espacios_estudiante' => ['anticipacion', 'Espacios, para estudiantes', 'minutos', 1440, 0, 20160],
        'anticipacion_equipos_docente' => ['anticipacion', 'Equipos, para docentes', 'minutos', 60, 0, 20160],
        'anticipacion_espacios_docente' => ['anticipacion', 'Espacios, para docentes', 'minutos', 120, 0, 20160],
    ];

    /**
     * Valor guardado, o el valor por defecto si todavía no existe.
     * No se guarda en memoria a propósito: así un cambio del administrador se aplica de inmediato.
     */
    public static function valor(string $clave): int
    {
        $guardado = static::where('clave', $clave)->value('valor');

        return (int) ($guardado ?? self::VALORES[$clave][3]);
    }

    /**
     * Todos los valores: clave => número.
     *
     * @return array<string, int>
     */
    public static function todos(): array
    {
        $guardados = static::pluck('valor', 'clave');

        return collect(self::VALORES)->map(fn ($definicion, $clave) => (int) ($guardados[$clave] ?? $definicion[3]))->all();
    }

    /** Máximo de préstamos aprobados en curso de un tipo (equipo o espacio) para el rol del usuario. */
    public static function maximo(User $user, bool $espacio): int
    {
        return self::valor(($espacio ? 'max_espacios_' : 'max_equipos_').$user->rol);
    }

    /** Minutos antes de la hora de inicio hasta los que el usuario puede cancelar. */
    public static function minutosCancelacion(User $user): int
    {
        return self::valor('cancelacion_'.$user->rol);
    }

    /** Minutos de anticipación mínima para que el usuario pida un equipo o un espacio. */
    public static function minutosAnticipacion(User $user, bool $espacio): int
    {
        return self::valor(($espacio ? 'anticipacion_espacios_' : 'anticipacion_equipos_').$user->rol);
    }

    /** Ejemplo: 90 → «1 h 30 min»; 1440 → «24 h»; 45 → «45 min». */
    public static function duracion(int $minutos): string
    {
        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;

        return match (true) {
            $horas === 0 => "{$resto} min",
            $resto === 0 => "{$horas} h",
            default => "{$horas} h {$resto} min",
        };
    }
}
