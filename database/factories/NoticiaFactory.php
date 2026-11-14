<?php

namespace Database\Factories;

use App\Models\Noticia;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Noticias inventadas para las pruebas.
 *
 * @extends Factory<Noticia>
 */
class NoticiaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['rol' => 'funcionario']),
            'titulo' => 'Aviso '.fake()->unique()->numerify('###').': '.fake()->words(3, true),
            'contenido' => fake()->paragraph(3),
        ];
    }
}
