<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UsuarioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Usuarios de demostración, uno por rol. La contraseña no es real: solo sirve en local.
        $usuarios = [
            ['Administrador de prueba', 'administrador@prestamos.test', 'administrador', '1000000001', 'Dependencia de préstamos'],
            ['Funcionario de prueba', 'funcionario@prestamos.test', 'funcionario', '1000000002', 'Dependencia de préstamos'],
            ['Docente de prueba', 'docente@prestamos.test', 'docente', '1000000003', 'Ingeniería de Sistemas'],
            ['Estudiante de prueba', 'estudiante@prestamos.test', 'estudiante', '1000000004', 'Ingeniería de Sistemas'],
        ];

        foreach ($usuarios as [$nombre, $correo, $rol, $documento, $programa]) {
            User::factory()->create([
                'name' => $nombre,
                'email' => $correo,
                'rol' => $rol,
                'documento' => $documento,
                'programa_dependencia' => $programa,
            ]);
        }
    }
}
