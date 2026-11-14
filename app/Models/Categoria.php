<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    /**
     * Íconos que se pueden elegir para una categoría (nombre en x-icono => etiqueta del formulario).
     */
    public const ICONOS = [
        'equipos' => 'Equipo general',
        'portatil' => 'Portátil',
        'proyector' => 'Proyector',
        'cable' => 'Cables',
        'audio' => 'Audio',
        'tableta' => 'Tableta',
        'camara' => 'Cámara',
        'periferico' => 'Periférico',
        'salon' => 'Salón o espacio',
    ];

    protected $fillable = ['nombre', 'descripcion', 'tipo', 'icono'];

    protected $attributes = [
        'tipo' => 'equipo',
    ];

    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class);
    }

    public function esEspacio(): bool
    {
        return $this->tipo === 'espacio';
    }

    /**
     * Ícono que se muestra: el elegido o, si no hay, uno según el tipo.
     */
    public function iconoVisible(): string
    {
        return $this->icono ?? ($this->esEspacio() ? 'salon' : 'equipos');
    }
}
