<?php

namespace Database\Seeders;

use App\Models\Programa;
use App\Models\User;
use Illuminate\Database\Seeder;

class UsuarioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ingeniería de Sistemas, sede Bucaramanga (SNIES 101596). La carga ProgramaSeeder.
        $sistemas = Programa::where('snies', 101596)->value('id');

        // Usuarios de demostración, uno por rol (y un segundo estudiante). La contraseña no es real: solo sirve en local.
        // El personal tiene dependencia; estudiantes y docentes, programa.
        $usuarios = [
            ['Administrador', 'administrador@prestamos.test', 'administrador', '1000000001', null, 'Dependencia de préstamos'],
            ['Funcionario', 'funcionario@prestamos.test', 'funcionario', '1000000002', null, 'Dependencia de préstamos'],
            ['Docente', 'docente@prestamos.test', 'docente', '1000000003', $sistemas, null],
            ['Estudiante', 'estudiante@prestamos.test', 'estudiante', '1000000004', $sistemas, null],
            // Segundo estudiante de la misma carrera, para las solicitudes de ejemplo (SolicitudSeeder).
            ['Estudiante 2', 'estudiante2@prestamos.test', 'estudiante', '1000000005', $sistemas, null],
        ];

        foreach ($usuarios as [$nombre, $correo, $rol, $documento, $programa, $dependencia]) {
            User::factory()->create([
                'name' => $nombre,
                'email' => $correo,
                'rol' => $rol,
                'tipo_documento' => 'CC',
                'documento' => $documento,
                'programa_id' => $programa,
                'dependencia' => $dependencia,
            ]);
        }
    }
}
