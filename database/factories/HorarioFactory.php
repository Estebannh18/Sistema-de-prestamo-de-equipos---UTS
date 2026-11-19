<?php

namespace Database\Factories;

use App\Models\Horario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Bloques de horario para las pruebas. El espacio o equipo se indica al crearlo.
 *
 * @extends Factory<Horario>
 */
class HorarioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dia_semana' => 1,
            'hora_inicio' => '07:30',
            'hora_fin' => '09:00',
        ];
    }
}
