<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;

class CategoriaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Usa updateOrCreate: se puede volver a ejecutar sobre una base que ya tiene datos.
     */
    public function run(): void
    {
        $categorias = [
            ['Portátiles', 'equipo', 'portatil', 'Computadores portátiles para clases, exposiciones y trabajo en grupo.'],
            ['Proyectores', 'equipo', 'proyector', 'Video beams portátiles con conexión HDMI y VGA.'],
            ['Cables', 'equipo', 'cable', 'Cables HDMI, adaptadores y extensiones eléctricas.'],
            ['Audio', 'equipo', 'audio', 'Parlantes y micrófonos para clases, eventos y sustentaciones.'],
            ['Tabletas', 'equipo', 'tableta', 'Tabletas para consulta, encuestas y actividades en campo.'],
            ['Cámaras', 'equipo', 'camara', 'Cámaras y trípodes para proyectos audiovisuales.'],
            ['Periféricos', 'equipo', 'periferico', 'Presentadores, mouse, teclados y cámaras web.'],
            ['Salones', 'espacio', 'salon', 'Aulas de clase con tablero y video beam instalado.'],
            ['Laboratorios', 'espacio', 'equipos', 'Salas de cómputo con equipos de escritorio.'],
        ];

        foreach ($categorias as [$nombre, $tipo, $icono, $descripcion]) {
            Categoria::updateOrCreate(
                ['nombre' => $nombre],
                ['tipo' => $tipo, 'icono' => $icono, 'descripcion' => $descripcion],
            );
        }
    }
}
