<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\HorarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un bloque de horas (inicio y fin) en que se presta un espacio o un equipo, un día de la semana.
 */
class Horario extends Model
{
    /** @use HasFactory<HorarioFactory> */
    use HasFactory;

    /** Días en que se presta: de lunes a sábado. Los domingos la universidad no labora. */
    public const DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'];

    /** Bloques de la jornada institucional, para cargarlos con un clic al configurar un día. */
    public const BLOQUES_INSTITUCIONALES = [
        ['06:00', '07:30'], ['07:30', '09:00'], ['09:00', '10:30'], ['10:30', '12:00'],
        ['13:30', '15:00'], ['15:00', '16:30'], ['16:30', '18:00'],
        ['18:30', '20:00'], ['20:00', '21:30'],
    ];

    protected $fillable = ['dia_semana', 'hora_inicio', 'hora_fin'];

    protected function casts(): array
    {
        return ['dia_semana' => 'integer'];
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }

    /** Hora de inicio como «07:30». */
    public function inicio(): string
    {
        return substr($this->hora_inicio, 0, 5);
    }

    /** Hora de fin como «09:00». */
    public function fin(): string
    {
        return substr($this->hora_fin, 0, 5);
    }

    /** Ejemplo: «7:30 a. m. – 9:00 a. m.». */
    public function etiqueta(): string
    {
        return self::rango($this->inicio(), $this->fin());
    }

    /** Fecha y hora en que empieza este bloque en un día dado. */
    public function inicioEn(CarbonInterface $fecha): Carbon
    {
        return Carbon::parse($fecha->toDateString().' '.$this->inicio());
    }

    /** Día de la semana de una fecha en el formato de esta tabla (1 = lunes … 7 = domingo). */
    public static function diaDe(CarbonInterface $fecha): int
    {
        return $fecha->dayOfWeekIso;
    }

    /** Ejemplo: «7:30 a. m. – 9:00 a. m.». */
    public static function rango(string $inicio, string $fin): string
    {
        return self::hora($inicio).' – '.self::hora($fin);
    }

    /** «13:30» → «1:30 p. m.». */
    public static function hora(string $hora): string
    {
        return str_replace(['am', 'pm'], ['a. m.', 'p. m.'], Carbon::createFromFormat('H:i', substr($hora, 0, 5))->format('g:i a'));
    }
}
