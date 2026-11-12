<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prestamo extends Model
{
    protected $fillable = [
        'equipo_id',
        'solicitante_id',
        'funcionario_id',
        'estado',
        'fecha_esperada_devolucion',
        'fecha_respuesta',
        'fecha_devolucion',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_esperada_devolucion' => 'date',
            'fecha_respuesta' => 'datetime',
            'fecha_devolucion' => 'datetime',
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

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'funcionario_id');
    }

    /**
     * Vencido no se guarda como estado: es un préstamo aprobado con la fecha esperada ya pasada.
     */
    public function estaVencido(): bool
    {
        return $this->estado === 'aprobado' && $this->fecha_esperada_devolucion->lt(today());
    }
}
