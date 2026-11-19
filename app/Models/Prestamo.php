<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una solicitud de préstamo: un solo espacio o equipo, para una fecha y un bloque de horas.
 * Estados: pendiente (espera al funcionario), aprobado, rechazado, cancelado (por el solicitante) y devuelto.
 */
class Prestamo extends Model
{
    /** @use HasFactory<\Database\Factories\PrestamoFactory> */
    use HasFactory;

    /** Estados que ocupan el espacio o equipo o que todavía esperan respuesta. */
    public const ACTIVOS = ['pendiente', 'aprobado'];

    protected $fillable = [
        'equipo_id',
        'solicitante_id',
        'funcionario_id',
        'estado',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'aprobacion',
        'fecha_esperada_devolucion',
        'fecha_respuesta',
        'fecha_devolucion',
        'cancelado_en',
        'observaciones',
        'motivo_rechazo',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_esperada_devolucion' => 'date',
            'fecha_respuesta' => 'datetime',
            'fecha_devolucion' => 'datetime',
            'cancelado_en' => 'datetime',
        ];
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }

    /** Quién la atendió (aprobó o rechazó). Vacío si se aprobó sola. */
    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'funcionario_id');
    }

    // ---------- Consultas ----------

    /** Pendientes o aprobadas cuyo bloque todavía no termina. */
    public function scopeEnCurso(Builder $query): Builder
    {
        $ahora = now();

        return $query->whereIn('estado', self::ACTIVOS)->where(fn (Builder $query) => $query
            ->where('fecha', '>', $ahora->toDateString())
            ->orWhere(fn (Builder $query) => $query->where('fecha', $ahora->toDateString())->where('hora_fin', '>', $ahora->format('H:i:s'))));
    }

    /** Del mismo espacio o equipo, el mismo día, y con horas que se cruzan con las dadas. */
    public function scopeQueSeCruzan(Builder $query, int $equipoId, string $fecha, string $inicio, string $fin): Builder
    {
        return $query->where('equipo_id', $equipoId)
            ->whereDate('fecha', $fecha)
            ->where('hora_inicio', '<', $fin)
            ->where('hora_fin', '>', $inicio);
    }

    /** Solo de espacios (salones, laboratorios…) o solo de equipos. */
    public function scopeDeTipo(Builder $query, bool $espacios): Builder
    {
        return $query->whereHas('equipo.categoria', fn (Builder $query) => $query->where('tipo', $espacios ? 'espacio' : 'equipo'));
    }

    // ---------- Fechas y horas ----------

    public function inicio(): ?Carbon
    {
        return $this->fecha && $this->hora_inicio ? Carbon::parse($this->fecha->toDateString().' '.$this->hora_inicio) : null;
    }

    public function fin(): ?Carbon
    {
        return $this->fecha && $this->hora_fin ? Carbon::parse($this->fecha->toDateString().' '.$this->hora_fin) : null;
    }

    /** Ejemplo: «7:30 a. m. – 9:00 a. m.». */
    public function bloque(): string
    {
        return $this->hora_inicio && $this->hora_fin ? Horario::rango($this->hora_inicio, $this->hora_fin) : '—';
    }

    /** Ejemplo: «martes 24/11/2026». */
    public function fechaLarga(): string
    {
        return $this->fecha ? mb_strtolower(Horario::DIAS[$this->fecha->dayOfWeekIso] ?? 'domingo').' '.$this->fecha->format('d/m/Y') : '—';
    }

    // ---------- Estado ----------

    public function esActivo(): bool
    {
        return in_array($this->estado, self::ACTIVOS, true);
    }

    public function estaEnCurso(): bool
    {
        return $this->esActivo() && $this->fin()?->isFuture();
    }

    /** Hasta cuándo el solicitante puede cancelar: el tiempo configurado antes de la hora de inicio. */
    public function limiteCancelacion(): ?Carbon
    {
        return $this->inicio()?->subMinutes(Configuracion::minutosCancelacion($this->solicitante));
    }

    public function sePuedeCancelar(): bool
    {
        return $this->esActivo() && ($limite = $this->limiteCancelacion()) !== null && now()->lessThanOrEqualTo($limite);
    }

    /**
     * Vencido no se guarda como estado: es un préstamo aprobado con la fecha esperada ya pasada.
     */
    public function estaVencido(): bool
    {
        return $this->estado === 'aprobado' && $this->fecha_esperada_devolucion->lt(today());
    }
}
