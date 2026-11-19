<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\Programa;
use Illuminate\Database\Seeder;

class EquipoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Se puede volver a ejecutar: actualiza los datos de cada código, pero el estado solo se asigna
     * al crear el registro, para no pisar el de uno que ya está prestado.
     * Nada nace prestado: ese estado solo lo pone un préstamo aprobado.
     * Los espacios y su ubicación son datos de prueba del caso simulado.
     */
    public function run(): void
    {
        // [categoría, código, nombre, marca, modelo, estado inicial, quién puede pedirlo, descripción]
        $equipos = [
            ['Portátiles', 'POR-001', 'Portátil Lenovo ThinkPad', 'Lenovo', 'ThinkPad E14 Gen 5', 'disponible', 'ambos', 'Intel Core i5, 16 GB de RAM, cargador incluido.'],
            ['Portátiles', 'POR-002', 'Portátil Lenovo ThinkPad', 'Lenovo', 'ThinkPad E14 Gen 5', 'mantenimiento', 'ambos', 'Teclado en revisión.'],
            ['Portátiles', 'POR-003', 'Portátil HP ProBook', 'HP', 'ProBook 440 G10', 'disponible', 'ambos', 'Intel Core i5, 16 GB de RAM.'],
            ['Portátiles', 'POR-004', 'Portátil HP ProBook', 'HP', 'ProBook 440 G10', 'disponible', 'ambos', 'Intel Core i5, 16 GB de RAM.'],
            ['Portátiles', 'POR-005', 'Portátil Dell Latitude', 'Dell', 'Latitude 3440', 'disponible', 'ambos', 'Intel Core i7, 16 GB de RAM.'],
            ['Portátiles', 'POR-006', 'Portátil Dell Latitude', 'Dell', 'Latitude 3440', 'disponible', 'docente', 'Intel Core i7, 16 GB de RAM.'],
            ['Portátiles', 'POR-007', 'Portátil Acer Aspire', 'Acer', 'Aspire 5', 'disponible', 'estudiante', 'Intel Core i5, 8 GB de RAM.'],
            ['Portátiles', 'POR-008', 'Portátil Lenovo IdeaPad', 'Lenovo', 'IdeaPad Slim 3', 'disponible', 'estudiante', 'AMD Ryzen 5, 8 GB de RAM.'],
            ['Portátiles', 'POR-009', 'Portátil Asus Vivobook', 'Asus', 'Vivobook 15', 'disponible', 'ambos', 'Intel Core i5, 12 GB de RAM.'],

            ['Proyectores', 'PRO-001', 'Video beam Epson', 'Epson', 'PowerLite X49', 'disponible', 'docente', '3.600 lúmenes, HDMI y VGA.'],
            ['Proyectores', 'PRO-002', 'Video beam Epson', 'Epson', 'PowerLite E20', 'disponible', 'docente', '3.400 lúmenes, HDMI.'],
            ['Proyectores', 'PRO-003', 'Video beam BenQ', 'BenQ', 'MW560', 'disponible', 'docente', '4.000 lúmenes, HDMI y VGA.'],
            ['Proyectores', 'PRO-004', 'Video beam ViewSonic', 'ViewSonic', 'PA503W', 'mantenimiento', 'docente', 'Cambio de lámpara pendiente.'],
            ['Proyectores', 'PRO-005', 'Video beam Epson', 'Epson', 'PowerLite W52', 'disponible', 'ambos', '4.000 lúmenes, HDMI y conexión inalámbrica.'],

            ['Cables', 'CAB-001', 'Cable HDMI 5 m', null, null, 'disponible', 'estudiante', null],
            ['Cables', 'CAB-002', 'Cable HDMI 10 m', null, null, 'disponible', 'ambos', null],
            ['Cables', 'CAB-003', 'Adaptador USB-C a HDMI', 'Ugreen', null, 'disponible', 'ambos', null],
            ['Cables', 'CAB-004', 'Adaptador HDMI a VGA', null, null, 'disponible', 'ambos', null],
            ['Cables', 'CAB-005', 'Extensión eléctrica 5 m', null, null, 'disponible', 'ambos', null],
            ['Cables', 'CAB-006', 'Multitoma de 6 tomas', null, null, 'mantenimiento', 'ambos', 'Interruptor dañado.'],
            ['Cables', 'CAB-007', 'Adaptador USB-C multipuerto', 'Anker', '7 en 1', 'disponible', 'ambos', 'HDMI, USB-A, lector de tarjetas y carga.'],
            ['Cables', 'CAB-008', 'Cable de red 10 m', null, 'Cat 6', 'disponible', 'ambos', null],

            ['Audio', 'AUD-001', 'Parlante activo JBL', 'JBL', 'EON 710', 'disponible', 'docente', 'Incluye cable de poder y cable XLR.'],
            ['Audio', 'AUD-002', 'Parlante portátil JBL', 'JBL', 'Charge 5', 'disponible', 'ambos', 'Bluetooth, batería de 20 horas.'],
            ['Audio', 'AUD-003', 'Micrófono inalámbrico Shure', 'Shure', 'BLX24/SM58', 'disponible', 'docente', 'Receptor y micrófono de mano.'],
            ['Audio', 'AUD-004', 'Micrófono de solapa Rode', 'Rode', 'Wireless GO II', 'disponible', 'docente', 'Dos transmisores y un receptor.'],
            ['Audio', 'AUD-005', 'Consola de sonido Behringer', 'Behringer', 'Xenyx 1202', 'disponible', 'docente', '12 canales.'],
            ['Audio', 'AUD-006', 'Diadema con micrófono Logitech', 'Logitech', 'H390', 'disponible', 'ambos', 'Conexión USB.'],

            ['Tabletas', 'TAB-001', 'Tableta Samsung Galaxy', 'Samsung', 'Galaxy Tab A9+', 'disponible', 'ambos', null],
            ['Tabletas', 'TAB-002', 'Tableta Samsung Galaxy', 'Samsung', 'Galaxy Tab A9+', 'disponible', 'ambos', null],
            ['Tabletas', 'TAB-003', 'Tableta Apple iPad', 'Apple', 'iPad (10.ª generación)', 'disponible', 'docente', null],
            ['Tabletas', 'TAB-004', 'Tableta Lenovo Tab', 'Lenovo', 'Tab M10', 'mantenimiento', 'ambos', 'Pantalla con rayones, en revisión.'],

            ['Cámaras', 'CAM-001', 'Cámara Canon EOS', 'Canon', 'EOS Rebel T7', 'disponible', 'ambos', 'Lente 18-55 mm, batería y cargador.'],
            ['Cámaras', 'CAM-002', 'Cámara Sony', 'Sony', 'ZV-E10', 'mantenimiento', 'docente', 'Revisión del sensor.'],
            ['Cámaras', 'CAM-003', 'Trípode Manfrotto', 'Manfrotto', 'Compact Action', 'disponible', 'ambos', null],
            ['Cámaras', 'CAM-004', 'Cámara de acción GoPro', 'GoPro', 'HERO12 Black', 'disponible', 'docente', 'Incluye carcasa sumergible.'],
            ['Cámaras', 'CAM-005', 'Kit de iluminación LED', 'Neewer', null, 'disponible', 'ambos', 'Dos paneles con trípode.'],

            ['Periféricos', 'PER-001', 'Presentador inalámbrico Logitech', 'Logitech', 'R500s', 'disponible', 'ambos', null],
            ['Periféricos', 'PER-002', 'Mouse inalámbrico Logitech', 'Logitech', 'M185', 'disponible', 'estudiante', null],
            ['Periféricos', 'PER-003', 'Teclado Logitech', 'Logitech', 'K120', 'disponible', 'estudiante', null],
            ['Periféricos', 'PER-004', 'Cámara web Logitech', 'Logitech', 'C920', 'disponible', 'ambos', null],
            ['Periféricos', 'PER-005', 'Tableta digitalizadora Wacom', 'Wacom', 'One', 'disponible', 'ambos', 'Para dibujo y diseño digital.'],
            ['Periféricos', 'PER-006', 'Disco duro externo 1 TB', 'Seagate', 'Expansion', 'disponible', 'docente', null],
        ];

        foreach ($equipos as [$categoria, $codigo, $nombre, $marca, $modelo, $estado, $solicitablePor, $descripcion]) {
            $this->guardar($codigo, $estado, [
                'categoria_id' => Categoria::where('nombre', $categoria)->value('id'),
                'nombre' => $nombre,
                'marca' => $marca,
                'modelo' => $modelo,
                'solicitable_por' => $solicitablePor,
                'descripcion' => $descripcion,
            ]);
        }

        // Espacios. Los docentes piden cualquiera; los estudiantes, solo los habilitados para su carrera.
        // [categoría, código, nombre, ubicación, capacidad, estado inicial, descripción, carreras habilitadas]
        // ['*'] = todas las carreras; [] = ninguna (solo docentes).
        $espacios = [
            ['Salones', 'SAL-301', 'Salón 301', 'Edificio A, piso 3', 40, 'disponible', 'Video beam instalado.', ['*']],
            ['Salones', 'SAL-302', 'Salón 302', 'Edificio A, piso 3', 35, 'disponible', null, ['*']],
            ['Salones', 'SAL-303', 'Salón 303', 'Edificio A, piso 3', 40, 'disponible', 'Video beam instalado.', ['*']],
            ['Salones', 'SAL-401', 'Salón 401', 'Edificio A, piso 4', 45, 'mantenimiento', 'Adecuación del sistema de sonido.', []],
            ['Salones', 'SAL-402', 'Salón de dibujo técnico', 'Edificio B, piso 4', 30, 'disponible', 'Mesas de dibujo y tablero amplio.', [
                'Ingeniería Civil', 'Ingeniería en Topografía', 'Tecnología en Construcción de Obras Civiles', 'Ingeniería Industrial',
            ]],
            ['Salones', 'SAL-501', 'Sala de juntas', 'Edificio C, piso 5', 12, 'disponible', 'Mesa de reuniones y pantalla de 65 pulgadas.', []],

            ['Laboratorios', 'LAB-201', 'Laboratorio de cómputo 201', 'Edificio B, piso 2', 25, 'disponible', '25 equipos de escritorio con paquete ofimático.', ['*']],
            ['Laboratorios', 'LAB-202', 'Laboratorio de cómputo 202', 'Edificio B, piso 2', 30, 'disponible', '30 equipos de escritorio con paquete ofimático.', ['*']],
            ['Laboratorios', 'LAB-301', 'Laboratorio de Inteligencia Artificial', 'Edificio B, piso 3', 24, 'disponible', 'Equipos con GPU para entrenamiento de modelos y análisis de datos.', [
                'Ingeniería de Sistemas', 'Ingeniería en Inteligencia Artificial y Ciencia de Datos', 'Tecnología en Desarrollo de Sistemas Informáticos', 'Tecnología en Análisis y Gestión de Datos',
            ]],
            ['Laboratorios', 'LAB-302', 'Laboratorio de Química', 'Edificio C, piso 1', 20, 'disponible', 'Campana de extracción, reactivos y material de vidrio.', [
                'Ingeniería Ambiental', 'Tecnología en Manejo de Recursos Ambientales', 'Ingeniería Agroforestal', 'Tecnología en Gestión Agroindustrial',
            ]],
            ['Laboratorios', 'LAB-303', 'Laboratorio de Electrónica', 'Edificio C, piso 2', 20, 'disponible', 'Osciloscopios, fuentes de poder y entrenadores digitales.', [
                'Ingeniería Electrónica', 'Ingeniería de Telecomunicaciones', 'Tecnología en Implementación de Sistemas Electrónicos Industriales', 'Tecnología en Gestión de Sistemas de Telecomunicaciones',
            ]],
            ['Laboratorios', 'LAB-304', 'Laboratorio de Máquinas Eléctricas', 'Edificio C, piso 1', 18, 'disponible', 'Motores, transformadores y bancos de prueba.', [
                'Ingeniería Eléctrica', 'Ingeniería Electromecánica', 'Ingeniería en Energías', 'Tecnología en Instalación de Sistemas Eléctricos',
                'Tecnología en Operación y Mantenimiento Electromecánico', 'Tecnología en Gestión de Recursos Energéticos',
            ]],
            ['Laboratorios', 'LAB-305', 'Laboratorio de Topografía', 'Edificio D, piso 1', 20, 'disponible', 'Estaciones totales, niveles y GPS.', [
                'Ingeniería en Topografía', 'Tecnología en Levantamientos Topográficos', 'Ingeniería Civil',
            ]],
            ['Laboratorios', 'LAB-306', 'Laboratorio de Suelos y Materiales', 'Edificio D, piso 1', 16, 'mantenimiento', 'Calibración de la prensa de concreto.', [
                'Ingeniería Civil', 'Tecnología en Construcción de Obras Civiles',
            ]],
            ['Laboratorios', 'LAB-307', 'Laboratorio de Procesos Industriales', 'Edificio C, piso 3', 20, 'disponible', 'Línea de ensamble didáctica y estaciones de métodos y tiempos.', [
                'Ingeniería Industrial', 'Tecnología en Producción Industrial', 'Tecnología en Logística del Transporte', 'Ingeniería en Sistemas de Transporte',
            ]],
            ['Laboratorios', 'LAB-308', 'Sala de Simulación Contable y Financiera', 'Edificio A, piso 2', 30, 'disponible', 'Software contable y simulador de mercados.', [
                'Contaduría Pública', 'Administración Financiera', 'Tecnología en Gestión Bancaria y Financiera', 'Tecnología en Manejo de la Información Contable',
                'Tecnología en Gestión Contable', 'Economía',
            ]],
            ['Laboratorios', 'LAB-309', 'Taller de Diseño y Confección', 'Edificio B, piso 1', 20, 'disponible', 'Máquinas de coser industriales, maniquíes y mesas de corte.', [
                'Profesional en Diseño de Modas', 'Tecnología en Gestión de la Moda',
            ]],
            ['Laboratorios', 'LAB-310', 'Laboratorio de Fisiología del Ejercicio', 'Coliseo, primer nivel', 25, 'disponible', 'Bicicletas ergométricas, pulsómetros y báscula de composición corporal.', [
                'Profesional en Cultura Física y Deporte', 'Tecnología en Entrenamiento Deportivo',
            ]],
            ['Laboratorios', 'LAB-311', 'Sala de Negocios y Mercadeo', 'Edificio A, piso 2', 30, 'disponible', 'Cámara Gesell y sala de presentaciones.', [
                'Mercadeo', 'Tecnología en Mercadeo y Gestión Comercial', 'Administración de Empresas', 'Negocios Internacionales', 'Tecnología en Gestión Empresarial',
            ]],

            ['Auditorios', 'AUT-101', 'Auditorio principal', 'Edificio A, piso 1', 250, 'disponible', 'Sonido, video beam y transmisión en vivo.', []],
            ['Auditorios', 'AUT-102', 'Auditorio menor', 'Edificio A, piso 1', 90, 'disponible', 'Sonido y video beam.', []],
        ];

        foreach ($espacios as [$categoria, $codigo, $nombre, $ubicacion, $capacidad, $estado, $descripcion, $carreras]) {
            $espacio = $this->guardar($codigo, $estado, [
                'categoria_id' => Categoria::where('nombre', $categoria)->value('id'),
                'nombre' => $nombre,
                'marca' => null,
                'modelo' => null,
                'ubicacion' => $ubicacion,
                'capacidad' => $capacidad,
                'solicitable_por' => $carreras === [] ? 'docente' : 'ambos',
                'descripcion' => $descripcion,
            ]);

            // Se enlazan todas las sedes de cada carrera; ['*'] enlaza todos los programas.
            $programas = $carreras === ['*'] ? Programa::pluck('id') : Programa::whereIn('nombre', $carreras)->pluck('id');
            $espacio->programas()->sync($programas);
        }
    }

    /**
     * Crea o actualiza por código. El estado inicial solo se usa al crear.
     *
     * @param  array<string, mixed>  $datos
     */
    private function guardar(string $codigo, string $estado, array $datos): Equipo
    {
        $equipo = Equipo::firstOrNew(['codigo_inventario' => $codigo]);
        $equipo->fill($datos);

        if (! $equipo->exists) {
            $equipo->estado = $estado;
        }

        $equipo->save();

        return $equipo;
    }
}
