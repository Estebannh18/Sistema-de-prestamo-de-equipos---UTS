<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Entrega de una solicitud aprobada al solicitante, en persona. Todo se escribe en cada entrega.
 */
class Entrega extends Model
{
    /** Estados en que se entrega: clave => etiqueta. */
    public const ESTADOS = ['bueno' => 'Bueno', 'con_observaciones' => 'Con observaciones'];

    protected $fillable = ['prestamo_id', 'funcionario_id', 'entregado_en', 'estado', 'complementos', 'condiciones', 'observaciones'];

    protected function casts(): array
    {
        return ['entregado_en' => 'datetime'];
    }

    public function prestamo(): BelongsTo
    {
        return $this->belongsTo(Prestamo::class);
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'funcionario_id');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(PrestamoFoto::class);
    }

    public function estadoLegible(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }
}
