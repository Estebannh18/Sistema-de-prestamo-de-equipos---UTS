<?php

namespace Database\Factories;

use App\Models\Prestamo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Préstamos de prueba. El espacio o equipo y el solicitante se indican al crearlo.
 *
 * @extends Factory<Prestamo>
 */
class PrestamoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fecha = today()->addDays(2);

        return [
            'estado' => 'pendiente',
            'fecha' => $fecha,
            'hora_inicio' => '07:30:00',
            'hora_fin' => '09:00:00',
            'fecha_esperada_devolucion' => $fecha,
        ];
    }
}
