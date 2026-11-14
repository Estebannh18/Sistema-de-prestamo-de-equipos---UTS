<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipo extends Model
{
    protected $fillable = [
        'categoria_id',
        'codigo_inventario',
        'nombre',
        'marca',
        'modelo',
        'estado',
        'solicitable_por',
        'descripcion',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function prestamos(): HasMany
    {
        return $this->hasMany(Prestamo::class);
    }

    /**
     * Equipos que un estudiante o un docente puede ver y pedir (RF-06, RF-21).
     */
    public function scopeDisponiblesPara(Builder $query, User $user): Builder
    {
        return $query->where('estado', 'disponible')->whereIn('solicitable_por', [$user->rol, 'ambos']);
    }

    /**
     * Quién puede pedir el equipo, en palabras.
     */
    public function quienPuedePedir(): string
    {
        return match ($this->solicitable_por) {
            'docente' => 'Solo docentes',
            'estudiante' => 'Solo estudiantes',
            default => 'Docentes y estudiantes',
        };
    }

    public function estaDisponible(): bool
    {
        return $this->estado === 'disponible';
    }

    /**
     * Indica si el rol del usuario está habilitado para pedir este equipo.
     * El administrador y el funcionario no solicitan préstamos.
     */
    public function permiteSolicitud(User $user): bool
    {
        if (! $user->esDocente() && ! $user->esEstudiante()) {
            return false;
        }

        return $this->solicitable_por === 'ambos' || $this->solicitable_por === $user->rol;
    }
}
