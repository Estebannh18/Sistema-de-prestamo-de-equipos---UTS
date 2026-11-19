<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Festivos de Colombia (Ley 51 de 1983, «Ley Emiliani»), calculados para cualquier año:
 * - fijos: se celebran en su fecha;
 * - trasladables: si no caen en lunes, pasan al lunes siguiente;
 * - de Semana Santa: dependen del Domingo de Pascua (algunos también se trasladan al lunes).
 * Los días festivos no se prestan espacios ni equipos.
 */
class Festivos
{
    /** @var array<int, array<string, string>> Festivos ya calculados por año. */
    private static array $cache = [];

    /**
     * Festivos de un año: fecha (Y-m-d) => nombre, en orden.
     *
     * @return array<string, string>
     */
    public static function delAnio(int $anio): array
    {
        if (isset(self::$cache[$anio])) {
            return self::$cache[$anio];
        }

        $fecha = fn (int $mes, int $dia) => Carbon::create($anio, $mes, $dia)->startOfDay();
        $pascua = self::domingoDePascua($anio);

        $festivos = [
            [$fecha(1, 1), 'Año Nuevo'],
            [self::alLunes($fecha(1, 6)), 'Día de los Reyes Magos'],
            [self::alLunes($fecha(3, 19)), 'Día de San José'],
            [$pascua->copy()->subDays(3), 'Jueves Santo'],
            [$pascua->copy()->subDays(2), 'Viernes Santo'],
            [$fecha(5, 1), 'Día del Trabajo'],
            [self::alLunes($pascua->copy()->addDays(39)), 'Ascensión del Señor'],
            [self::alLunes($pascua->copy()->addDays(60)), 'Corpus Christi'],
            [self::alLunes($pascua->copy()->addDays(68)), 'Sagrado Corazón de Jesús'],
            [self::alLunes($fecha(6, 29)), 'San Pedro y San Pablo'],
            [$fecha(7, 20), 'Día de la Independencia'],
            [$fecha(8, 7), 'Batalla de Boyacá'],
            [self::alLunes($fecha(8, 15)), 'Asunción de la Virgen'],
            [self::alLunes($fecha(10, 12)), 'Día de la Diversidad Étnica y Cultural'],
            [self::alLunes($fecha(11, 1)), 'Día de Todos los Santos'],
            [self::alLunes($fecha(11, 11)), 'Independencia de Cartagena'],
            [$fecha(12, 8), 'Día de la Inmaculada Concepción'],
            [$fecha(12, 25), 'Navidad'],
        ];

        usort($festivos, fn ($a, $b) => $a[0] <=> $b[0]);

        return self::$cache[$anio] = collect($festivos)->mapWithKeys(fn ($f) => [$f[0]->toDateString() => $f[1]])->all();
    }

    /** Nombre del festivo de esa fecha, o null si es un día normal. */
    public static function nombre(Carbon $fecha): ?string
    {
        return self::delAnio($fecha->year)[$fecha->toDateString()] ?? null;
    }

    public static function esFestivo(Carbon $fecha): bool
    {
        return self::nombre($fecha) !== null;
    }

    /** Si la fecha no es lunes, el lunes siguiente. */
    private static function alLunes(Carbon $fecha): Carbon
    {
        return $fecha->isMonday() ? $fecha : $fecha->copy()->next(Carbon::MONDAY);
    }

    /** Domingo de Pascua (algoritmo de Meeus/Jones/Butcher para el calendario gregoriano). */
    private static function domingoDePascua(int $anio): Carbon
    {
        $a = $anio % 19;
        $b = intdiv($anio, 100);
        $c = $anio % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($anio, $mes, $dia)->startOfDay();
    }
}
