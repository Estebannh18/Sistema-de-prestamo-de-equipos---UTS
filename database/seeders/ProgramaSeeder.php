<?php

namespace Database\Seeders;

use App\Models\Programa;
use Illuminate\Database\Seeder;

/**
 * Programas académicos de la oferta oficial de las UTS.
 * Fuente: https://www.uts.edu.co/sitio/oferta-academica/ (consultada el 12/11/2026).
 *
 * Se puede correr cuantas veces se quiera: busca cada programa por su código SNIES
 * y lo actualiza si ya existe, así que nunca duplica ni borra.
 *   php artisan db:seed --class=ProgramaSeeder
 */
class ProgramaSeeder extends Seeder
{
    /** sede => [nivel => [SNIES => nombre]] */
    private const PROGRAMAS = [
        'Bucaramanga' => [
            'tecnologia' => [
                101597 => 'Tecnología en Desarrollo de Sistemas Informáticos',
                17664 => 'Tecnología en Entrenamiento Deportivo',
                109548 => 'Tecnología en Gestión Bancaria y Financiera',
                106408 => 'Tecnología en Gestión de la Moda',
                108284 => 'Tecnología en Gestión de Sistemas de Telecomunicaciones',
                11007 => 'Tecnología en Gestión Empresarial',
                109017 => 'Tecnología en Implementación de Sistemas Electrónicos Industriales',
                118041 => 'Tecnología en Instalación de Sistemas Eléctricos',
                111645 => 'Tecnología en Logística del Transporte',
                110921 => 'Tecnología en Manejo de la Información Contable',
                101724 => 'Tecnología en Manejo de Recursos Ambientales',
                103073 => 'Tecnología en Mercadeo y Gestión Comercial',
                102378 => 'Tecnología en Operación y Mantenimiento Electromecánico',
                107256 => 'Tecnología en Producción Industrial',
            ],
            'universitario' => [
                53456 => 'Administración de Empresas',
                109549 => 'Administración Financiera',
                90894 => 'Contaduría Pública',
                101707 => 'Ingeniería Ambiental',
                101596 => 'Ingeniería de Sistemas',
                53092 => 'Ingeniería de Telecomunicaciones',
                106412 => 'Ingeniería Eléctrica',
                90937 => 'Ingeniería Electromecánica',
                53390 => 'Ingeniería Electrónica',
                111646 => 'Ingeniería en Sistemas de Transporte',
                109226 => 'Ingeniería en Topografía',
                107257 => 'Ingeniería Industrial',
                110617 => 'Mercadeo',
                110922 => 'Profesional en Cultura Física y Deporte',
                106407 => 'Profesional en Diseño de Modas',
            ],
        ],
        'Piedecuesta' => [
            'tecnologia' => [
                111452 => 'Tecnología en Construcción de Obras Civiles',
                10349 => 'Tecnología en Gestión Agroindustrial',
                116930 => 'Tecnología en Gestión Económica de Proyectos',
                109015 => 'Tecnología en Levantamientos Topográficos',
            ],
            'universitario' => [
                116932 => 'Economía',
                111356 => 'Ingeniería Civil',
            ],
        ],
        'Vélez' => [
            'tecnologia' => [
                116905 => 'Tecnología en Gestión Empresarial',
                117163 => 'Tecnología en Manejo de la Información Contable',
                111742 => 'Tecnología en Manejo de Sistemas Agroforestales',
            ],
            'universitario' => [
                116816 => 'Administración de Empresas',
                117161 => 'Contaduría Pública',
                111743 => 'Ingeniería Agroforestal',
            ],
        ],
        'Barrancabermeja' => [
            'tecnologia' => [
                111122 => 'Tecnología en Gestión de Recursos Energéticos',
                90358 => 'Tecnología en Gestión Empresarial',
                116931 => 'Tecnología en Manejo de la Información Contable',
                90531 => 'Tecnología en Operación y Mantenimiento Electromecánico',
            ],
            'universitario' => [
                103265 => 'Administración de Empresas',
                116768 => 'Contaduría Pública',
                103234 => 'Ingeniería Electromecánica',
                111123 => 'Ingeniería en Energías',
            ],
        ],
        'Campus Virtual' => [
            'tecnologia' => [
                119109 => 'Tecnología en Análisis y Gestión de Datos',
                118008 => 'Tecnología en Gestión Contable',
                119347 => 'Tecnología en Gestión del Comercio Exterior',
                107179 => 'Tecnología en Gestión Empresarial',
                118606 => 'Tecnología en Gestión Turística y Hotelera',
            ],
            'universitario' => [
                107432 => 'Administración de Empresas',
                118607 => 'Administración de Empresas Turísticas y Hoteleras',
                107060 => 'Contaduría Pública',
                119105 => 'Ingeniería en Inteligencia Artificial y Ciencia de Datos',
                119351 => 'Negocios Internacionales',
            ],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::PROGRAMAS as $sede => $niveles) {
            foreach ($niveles as $nivel => $programas) {
                foreach ($programas as $snies => $nombre) {
                    Programa::updateOrCreate(['snies' => $snies], [
                        'nombre' => $nombre,
                        'nivel' => $nivel,
                        'modalidad' => $sede === Programa::SEDE_VIRTUAL ? 'virtual' : 'presencial',
                        'sede' => $sede,
                    ]);
                }
            }
        }
    }
}
