<?php

namespace App\Models;

use Database\Factories\PrestamoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Una solicitud de préstamo: un solo espacio o equipo, para una fecha y un bloque de horas.
 * Estados: pendiente (espera al funcionario), aprobado (reservado), rechazado, cancelado (por el solicitante,
 * el personal o el sistema), entregado, no recogido (el bloque terminó sin entrega), no entregado
 * (no se pudo entregar), por corregir (la devolución no se recibió todavía), devuelto y devolución rechazada.
 */
class Prestamo extends Model
{
    /** @use HasFactory<PrestamoFactory> */
    use HasFactory;

    /** Estados que ocupan el espacio o equipo o que todavía esperan respuesta. */
    public const ACTIVOS = ['pendiente', 'aprobado', 'entregado'];

    /** Estados que ocupan el bloque: nadie más lo puede pedir. */
    public const RESERVADOS = ['aprobado', 'entregado'];

    /** Estados que todavía se pueden cancelar (antes de la entrega). */
    public const CANCELABLES = ['pendiente', 'aprobado'];

    /** Nombre de cada estado para mostrarlo: clave => etiqueta. */
    public const NOMBRES = [
        'pendiente' => 'Pendiente de aprobación',
        'aprobado' => 'Aprobada',
        'rechazado' => 'Rechazada',
        'cancelado' => 'Cancelada',
        'entregado' => 'Entregado',
        'no_recogido' => 'No recogido',
        'no_entregado' => 'No entregado',
        'por_corregir' => 'Por corregir',
        'devuelto' => 'Devuelto',
        'devolucion_rechazada' => 'Devolución rechazada',
    ];

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
        'cancelada_por',
        'cancelado_por_id',
        'motivo_cancelacion',
        'motivo_no_entrega',
        'no_entregado_por_id',
        'no_entregado_en',
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
            'no_entregado_en' => 'datetime',
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

    public function entrega(): HasOne
    {
        return $this->hasOne(Entrega::class);
    }

    /** Revisiones de la devolución, de la más antigua a la más reciente. */
    public function devoluciones(): HasMany
    {
        return $this->hasMany(Devolucion::class)->orderBy('recibido_en')->orderBy('id');
    }

    public function ultimaDevolucion(): HasOne
    {
        return $this->hasOne(Devolucion::class)->latestOfMany('id');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(PrestamoFoto::class);
    }

    public function noEntregadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'no_entregado_por_id');
    }

    /** Quién la canceló: el solicitante o alguien del personal. Vacío si la canceló el sistema. */
    public function canceladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelado_por_id');
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
        return in_array($this->estado, self::CANCELABLES, true) && ($limite = $this->limiteCancelacion()) !== null && now()->lessThanOrEqualTo($limite);
    }

    public function nombreEstado(): string
    {
        return self::NOMBRES[$this->estado] ?? $this->estado;
    }

    // ---------- Entrega y devolución ----------

    /** Desde cuándo se puede entregar: el tiempo configurado antes del inicio del bloque. */
    public function inicioEntrega(): ?Carbon
    {
        return $this->inicio()?->subMinutes(Configuracion::minutosAnticipacionEntrega());
    }

    /** Hasta cuándo se devuelve sin retraso: el tiempo configurado después del fin del bloque. */
    public function limiteDevolucion(): ?Carbon
    {
        return $this->fin()?->addMinutes(Configuracion::minutosDevolucion());
    }

    /** Aprobada y dentro del horario de entrega (desde la anticipación hasta el fin del bloque). */
    public function enHorarioDeEntrega(): bool
    {
        return $this->estado === 'aprobado' && now()->betweenIncluded($this->inicioEntrega(), $this->fin());
    }

    /** Entregado y ya pasó la hora límite de devolución: «devolución atrasada» (se calcula, no es un estado). */
    public function estaAtrasado(): bool
    {
        return $this->estado === 'entregado' && now()->greaterThan($this->limiteDevolucion());
    }

    /** Minutos de retraso si se recibe ahora (0 si está a tiempo). */
    public function minutosDeRetraso(): int
    {
        $limite = $this->limiteDevolucion();

        return $limite && now()->greaterThan($limite) ? (int) ceil($limite->diffInSeconds(now()) / 60) : 0;
    }

    /** Devuelto, pero con algo por resolver (por ejemplo, una pieza que faltó). */
    public function tienePendiente(): bool
    {
        return $this->estado === 'devuelto' && (bool) $this->ultimaDevolucion?->tienePendienteSinResolver();
    }

    /**
     * Las aprobadas cuyo bloque ya terminó sin entrega pasan a «No recogido». Se llama con frecuencia
     * (middleware), así no hace falta una tarea programada.
     */
    public static function marcarNoRecogidos(): int
    {
        $ahora = now();

        return DB::table('prestamos')->where('estado', 'aprobado')
            ->where(fn ($query) => $query->whereDate('fecha', '<', $ahora->toDateString())
                ->orWhere(fn ($query) => $query->whereDate('fecha', $ahora->toDateString())->where('hora_fin', '<=', $ahora->format('H:i:s'))))
            ->update(['estado' => 'no_recogido', 'updated_at' => $ahora]);
    }
}
