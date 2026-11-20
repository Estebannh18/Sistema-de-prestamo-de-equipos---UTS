<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ProgramaSeeder::class,
            UsuarioSeeder::class,
            CategoriaSeeder::class,
            EquipoSeeder::class,
            HorarioSeeder::class,
            SolicitudSeeder::class,
            EntregaSeeder::class,
            // Al final: SolicitudSeeder y EntregaSeeder eligen equipos que todavía no tienen préstamos.
            HistorialSeeder::class,
            NoticiaSeeder::class,
        ]);
    }
}
