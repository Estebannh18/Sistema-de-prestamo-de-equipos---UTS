<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Mantenimiento de un espacio o equipo: desde una fecha, hasta otra o sin fin, con su motivo.
 * En esas fechas no se puede pedir. El estado del equipo («mantenimiento» o no) se calcula con
 * el de hoy: sincronizarEstados() lo actualiza.
 */
class Mantenimiento extends Model
{
    use HasFactory;

    protected $fillable = ['equipo_id', 'desde', 'hasta', 'motivo', 'creado_por_id', 'prestamo_id'];

    protected function casts(): array
    {
        return [
            'desde' => 'date',
            'hasta' => 'date',
        ];
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    /** El préstamo cuya devolución rechazada lo originó, si es el caso. */
    public function prestamo(): BelongsTo
    {
        return $this->belongsTo(Prestamo::class);
    }

    // ---------- Consultas ----------

    /** Los que cubren ese día. */
    public function scopeVigenteEl(Builder $query, Carbon|string $fecha): Builder
    {
        $dia = $fecha instanceof Carbon ? $fecha->toDateString() : $fecha;

        return $query->whereDate('desde', '<=', $dia)->where(fn (Builder $query) => $query->whereNull('hasta')->orWhereDate('hasta', '>=', $dia));
    }

    /** Los que se cruzan con el rango [desde, hasta]; hasta vacío = sin fin. */
    public function scopeQueSeCruzan(Builder $query, string $desde, ?string $hasta): Builder
    {
        return $query->where(fn (Builder $query) => $query->whereNull('hasta')->orWhereDate('hasta', '>=', $desde))
            ->when($hasta, fn (Builder $query) => $query->whereDate('desde', '<=', $hasta));
    }

    /** Sin fecha de fin y ya empezó: el equipo está fuera de servicio. */
    public function scopeIndefinidoVigente(Builder $query): Builder
    {
        return $query->whereNull('hasta')->whereDate('desde', '<=', today()->toDateString());
    }

    // ---------- Ayudas ----------

    public function esIndefinido(): bool
    {
        return $this->hasta === null;
    }

    public function yaEmpezo(): bool
    {
        return $this->desde->lessThanOrEqualTo(today());
    }

    public function yaTermino(): bool
    {
        return $this->hasta !== null && $this->hasta->lessThan(today());
    }

    /** Ejemplo: «del 20/11/2026 al 26/11/2026» o «desde el 20/11/2026, sin fecha de fin». */
    public function periodo(): string
    {
        if ($this->hasta === null) {
            return 'desde el '.$this->desde->format('d/m/Y').', sin fecha de fin';
        }

        return $this->desde->equalTo($this->hasta)
            ? 'el '.$this->desde->format('d/m/Y')
            : 'del '.$this->desde->format('d/m/Y').' al '.$this->hasta->format('d/m/Y');
    }

    /**
     * Pone el estado de cada equipo según el día de hoy: «mantenimiento» si hoy tiene uno;
     * si ya no lo tiene, vuelve a «disponible» (o a «prestado» si está entregado a alguien).
     */
    public static function sincronizarEstados(): void
    {
        $hoy = today()->toDateString();
        $enMantenimiento = static::vigenteEl($hoy)->select('equipo_id');

        DB::table('equipos')->whereIn('id', $enMantenimiento)->where('estado', '!=', 'mantenimiento')->update(['estado' => 'mantenimiento']);

        DB::table('equipos')->where('estado', 'mantenimiento')->whereNotIn('id', $enMantenimiento)->update([
            'estado' => DB::raw("CASE WHEN EXISTS (SELECT 1 FROM prestamos WHERE prestamos.equipo_id = equipos.id AND prestamos.estado IN ('entregado', 'por_corregir')) THEN 'prestado' ELSE 'disponible' END"),
        ]);
    }
}
