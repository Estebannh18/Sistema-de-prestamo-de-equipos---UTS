<?php

namespace Database\Seeders;

use App\Models\Noticia;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Dos noticias de ejemplo, solo con texto. Se puede volver a correr sin duplicarlas:
 *   php artisan db:seed --class=NoticiaSeeder
 */
class NoticiaSeeder extends Seeder
{
    public function run(): void
    {
        $funcionario = User::where('email', 'funcionario@prestamos.test')->value('id');
        $administrador = User::where('email', 'administrador@prestamos.test')->value('id');

        $noticias = [
            [$administrador, 'Sala de informática 301C cerrada por mantenimiento', "La sala de informática 301C estará cerrada del lunes 16 al viernes 20 de noviembre por mantenimiento de los equipos.\n\nDurante esos días no se podrán hacer reservas de esa sala. Si necesitas un espacio con computadores, solicita la sala 302A."],
            [$funcionario, 'Equipos restaurados en el salón 304B', "Se restauraron los computadores y el videoproyector del salón 304B. Desde hoy el salón vuelve a estar disponible para préstamo en todos los bloques de horario."],
        ];

        foreach ($noticias as [$autor, $titulo, $contenido]) {
            $noticia = Noticia::firstOrNew(['titulo' => $titulo]);
            $noticia->contenido = $contenido;
            $noticia->user_id = $autor;
            $noticia->save();
        }
    }
}
