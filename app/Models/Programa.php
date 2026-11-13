<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Programa académico de las UTS (carrera). Los estudiantes y docentes pertenecen a uno.
 */
class Programa extends Model
{
    /** @use HasFactory<\Database\Factories\ProgramaFactory> */
    use HasFactory;

    /** Sedes en el orden en que se muestran en las listas. */
    public const SEDES = ['Bucaramanga', 'Piedecuesta', 'Vélez', 'Barrancabermeja', 'Campus Virtual'];

    public const SEDE_VIRTUAL = 'Campus Virtual';

    public const NIVELES = [
        'tecnologia' => 'Tecnología',
        'universitario' => 'Universitario',
    ];

    protected $fillable = ['nombre', 'nivel', 'modalidad', 'sede', 'snies'];

    protected function casts(): array
    {
        return [
            'snies' => 'integer',
        ];
    }

    /** Usuarios (estudiantes y docentes) de este programa. */
    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Todos los programas agrupados por sede (en el orden de SEDES) y por nombre,
     * listos para un <select> con <optgroup>.
     *
     * @return \Illuminate\Support\Collection<string, Collection<int, Programa>>
     */
    public static function agrupadosPorSede(): \Illuminate\Support\Collection
    {
        $orden = array_flip(self::SEDES);

        return self::query()->orderBy('nombre')->get()
            ->groupBy('sede')
            ->sortBy(fn ($programas, string $sede) => $orden[$sede] ?? count($orden));
    }

    /** Nombre de la sede como se muestra en el formulario. */
    public static function etiquetaSede(string $sede): string
    {
        return $sede === self::SEDE_VIRTUAL ? 'Campus Virtual (virtual)' : "Sede {$sede} (presencial)";
    }
}
