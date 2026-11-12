<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Equipo;
use Illuminate\Database\Seeder;

class EquipoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // solicitable_por indica quién puede pedir cada equipo: estudiante, docente o ambos.
        $equipos = [
            ['Portátiles', 'POR-001', 'Portátil 1', 'disponible', 'ambos'],
            ['Portátiles', 'POR-002', 'Portátil 2', 'mantenimiento', 'ambos'],
            ['Proyectores', 'PRO-001', 'Video beam 1', 'disponible', 'docente'],
            ['Cables', 'CAB-001', 'Cable HDMI 1', 'disponible', 'estudiante'],
            ['Salones', 'SAL-301', 'Salón 301', 'disponible', 'docente'],
            ['Salones', 'SAL-302', 'Salón 302', 'disponible', 'ambos'],
        ];

        foreach ($equipos as [$categoria, $codigo, $nombre, $estado, $solicitablePor]) {
            Equipo::create([
                'categoria_id' => Categoria::where('nombre', $categoria)->value('id'),
                'codigo_inventario' => $codigo,
                'nombre' => $nombre,
                'estado' => $estado,
                'solicitable_por' => $solicitablePor,
            ]);
        }
    }
}
