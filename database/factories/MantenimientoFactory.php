<?php

namespace Database\Factories;

use App\Models\Mantenimiento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Mantenimientos de prueba. El espacio o equipo se indica al crearlo.
 *
 * @extends Factory<Mantenimiento>
 */
class MantenimientoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'desde' => today(),
            'hasta' => today()->addDays(3),
            'motivo' => 'Mantenimiento preventivo.',
        ];
    }
}
