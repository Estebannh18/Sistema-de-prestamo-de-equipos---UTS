<?php

namespace Database\Factories;

use App\Models\Programa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Programas inventados para las pruebas. Los reales los carga ProgramaSeeder.
 *
 * @extends Factory<Programa>
 */
class ProgramaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Programa de prueba '.fake()->unique()->numerify('###'),
            'nivel' => fake()->randomElement(array_keys(Programa::NIVELES)),
            'modalidad' => 'presencial',
            'sede' => 'Bucaramanga',
            // Números altos para no chocar con los códigos SNIES reales que ya están cargados.
            'snies' => fake()->unique()->numberBetween(900000, 999999),
        ];
    }
}
