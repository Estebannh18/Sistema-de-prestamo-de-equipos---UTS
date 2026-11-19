<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Un equipo (portátil, video beam…) o un espacio (salón, laboratorio, auditorio).
 * Los dos se piden igual: por fecha y por un bloque de su horario.
 */
class Equipo extends Model
{
    protected $fillable = [
        'categoria_id',
        'codigo_inventario',
        'nombre',
        'marca',
        'modelo',
        'ubicacion',
        'capacidad',
        'estado',
        'solicitable_por',
        'descripcion',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /**
     * Carreras habilitadas de un espacio. Para estudiantes es una restricción: solo pueden pedir
     * el espacio si su carrera está aquí. Los docentes pueden pedir cualquier espacio.
     */
    public function programas(): BelongsToMany
    {
        return $this->belongsToMany(Programa::class);
    }

    /** Bloques de horas en que se presta, por día de la semana. */
    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class)->orderBy('dia_semana')->orderBy('hora_inicio');
    }

    public function esEspacio(): bool
    {
        return (bool) $this->categoria?->esEspacio();
    }

    /**
     * Nombres de las carreras habilitadas, sin repetir: un mismo programa existe en varias sedes.
     *
     * @return Collection<int, string>
     */
    public function carrerasHabilitadas(): Collection
    {
        return $this->programas->pluck('nombre')->unique()->sort()->values();
    }

    /** Si el espacio está habilitado para la carrera del usuario. */
    public function esDeLaCarreraDe(User $user): bool
    {
        return $user->programa_id !== null && $this->programas->contains('id', $user->programa_id);
    }

    public function prestamos(): HasMany
    {
        return $this->hasMany(Prestamo::class);
    }

    /**
     * Lo que un estudiante o un docente puede ver y pedir (RF-06, RF-21):
     * - equipos disponibles habilitados para su rol;
     * - espacios disponibles: el docente, todos; el estudiante, solo los habilitados para su carrera.
     */
    public function scopeDisponiblesPara(Builder $query, User $user): Builder
    {
        return $query->where('estado', 'disponible')->where(function (Builder $query) use ($user) {
            $query->where(fn (Builder $query) => $query
                ->whereHas('categoria', fn (Builder $query) => $query->where('tipo', 'equipo'))
                ->whereIn('solicitable_por', [$user->rol, 'ambos']))
                ->orWhere(fn (Builder $query) => $query
                    ->whereHas('categoria', fn (Builder $query) => $query->where('tipo', 'espacio'))
                    ->when($user->esEstudiante(), fn (Builder $query) => $query->whereHas('programas', fn (Builder $query) => $query->whereKey($user->programa_id ?? 0))));
        });
    }

    /**
     * Quién puede pedirlo, en palabras.
     */
    public function quienPuedePedir(): string
    {
        if ($this->esEspacio()) {
            $carreras = $this->carrerasHabilitadas()->count();

            return $carreras === 0 ? 'Solo docentes' : 'Docentes y estudiantes de '.$carreras.' '.($carreras === 1 ? 'carrera' : 'carreras');
        }

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
     * Indica si el usuario puede pedirlo. El administrador y el funcionario no solicitan préstamos.
     * Un espacio lo pide cualquier docente, y un estudiante solo si está habilitado para su carrera.
     */
    public function permiteSolicitud(User $user): bool
    {
        if (! $user->esDocente() && ! $user->esEstudiante()) {
            return false;
        }

        if ($this->esEspacio()) {
            return $user->esDocente() || $this->esDeLaCarreraDe($user);
        }

        return $this->solicitable_por === 'ambos' || $this->solicitable_por === $user->rol;
    }
}
