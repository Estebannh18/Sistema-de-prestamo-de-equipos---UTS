<?php

namespace Database\Seeders;

use App\Models\Equipo;
use App\Models\Horario;
use Illuminate\Database\Seeder;

/**
 * Horarios de ejemplo: a cada espacio o equipo que todavía no tiene horarios le pone los bloques
 * institucionales de lunes a viernes, y los de la mañana el sábado.
 * No toca los que ya tienen horarios, así que se puede volver a correr:
 *   php artisan db:seed --class=HorarioSeeder
 */
class HorarioSeeder extends Seeder
{
    public function run(): void
    {
        $manana = array_slice(Horario::BLOQUES_INSTITUCIONALES, 0, 4);

        Equipo::doesntHave('horarios')->each(function (Equipo $equipo) use ($manana) {
            foreach (Horario::DIAS as $dia => $nombre) {
                foreach ($dia === 6 ? $manana : Horario::BLOQUES_INSTITUCIONALES as [$inicio, $fin]) {
                    $equipo->horarios()->create(['dia_semana' => $dia, 'hora_inicio' => $inicio, 'hora_fin' => $fin]);
                }
            }
        });
    }
}
