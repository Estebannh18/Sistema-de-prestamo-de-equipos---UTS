<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;

class CategoriaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['Portátiles', 'Proyectores', 'Cables', 'Salones'] as $nombre) {
            Categoria::create(['nombre' => $nombre]);
        }
    }
}
