<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una revisión de la devolución: aceptada (con o sin pendiente), por corregir o rechazada.
 * Una solicitud puede tener varias, de la más antigua a la más reciente.
 */
class Devolucion extends Model
{
    protected $table = 'devoluciones';

    /** Estado en que se recibe: clave => etiqueta. */
    public const ESTADOS = ['bueno' => 'Bueno', 'sucio' => 'Sucio o desordenado', 'danado' => 'Dañado'];

    /** Resultado de la revisión: clave => etiqueta. */
    public const RESULTADOS = ['aceptada' => 'Aceptada', 'por_corregir' => 'Por corregir', 'rechazada' => 'Rechazada'];

    protected $fillable = [
        'prestamo_id', 'funcionario_id', 'recibido_en', 'minutos_retraso', 'estado', 'complementos', 'complementos_completos',
        'faltantes', 'condiciones_cumplidas', 'observaciones', 'resultado', 'motivo', 'plazo', 'pendiente',
        'pendiente_resuelto_en', 'pendiente_resuelto_por_id',
    ];

    protected function casts(): array
    {
        return [
            'recibido_en' => 'datetime',
            'pendiente_resuelto_en' => 'datetime',
            'complementos_completos' => 'boolean',
            'condiciones_cumplidas' => 'boolean',
            'minutos_retraso' => 'integer',
        ];
    }

    public function prestamo(): BelongsTo
    {
        return $this->belongsTo(Prestamo::class);
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'funcionario_id');
    }

    public function pendienteResueltoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pendiente_resuelto_por_id');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(PrestamoFoto::class);
    }

    public function estadoLegible(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function resultadoLegible(): string
    {
        return self::RESULTADOS[$this->resultado] ?? $this->resultado;
    }

    public function conRetraso(): bool
    {
        return $this->minutos_retraso > 0;
    }

    /** Aceptada, pero con algo por resolver que todavía no se ha resuelto. */
    public function tienePendienteSinResolver(): bool
    {
        return $this->resultado === 'aceptada' && filled($this->pendiente) && $this->pendiente_resuelto_en === null;
    }
}
