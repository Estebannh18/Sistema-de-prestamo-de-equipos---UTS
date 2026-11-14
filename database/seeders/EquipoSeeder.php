<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Equipo;
use Illuminate\Database\Seeder;

class EquipoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Se puede volver a ejecutar: actualiza los datos de cada código de inventario, pero el estado
     * solo se asigna al crear el equipo, para no pisar el de un equipo que ya está prestado.
     * Ningún equipo nace prestado: ese estado solo lo pone un préstamo aprobado.
     */
    public function run(): void
    {
        // [categoría, código, nombre, marca, modelo, estado inicial, quién puede pedirlo, descripción]
        // solicitable_por indica quién puede pedir cada equipo: estudiante, docente o ambos.
        $equipos = [
            ['Portátiles', 'POR-001', 'Portátil Lenovo ThinkPad', 'Lenovo', 'ThinkPad E14 Gen 5', 'disponible', 'ambos', 'Intel Core i5, 16 GB de RAM, cargador incluido.'],
            ['Portátiles', 'POR-002', 'Portátil Lenovo ThinkPad', 'Lenovo', 'ThinkPad E14 Gen 5', 'mantenimiento', 'ambos', 'Teclado en revisión.'],
            ['Portátiles', 'POR-003', 'Portátil HP ProBook', 'HP', 'ProBook 440 G10', 'disponible', 'ambos', 'Intel Core i5, 16 GB de RAM.'],
            ['Portátiles', 'POR-004', 'Portátil HP ProBook', 'HP', 'ProBook 440 G10', 'disponible', 'ambos', 'Intel Core i5, 16 GB de RAM.'],
            ['Portátiles', 'POR-005', 'Portátil Dell Latitude', 'Dell', 'Latitude 3440', 'disponible', 'ambos', 'Intel Core i7, 16 GB de RAM.'],
            ['Portátiles', 'POR-006', 'Portátil Dell Latitude', 'Dell', 'Latitude 3440', 'disponible', 'docente', 'Intel Core i7, 16 GB de RAM.'],
            ['Portátiles', 'POR-007', 'Portátil Acer Aspire', 'Acer', 'Aspire 5', 'disponible', 'estudiante', 'Intel Core i5, 8 GB de RAM.'],

            ['Proyectores', 'PRO-001', 'Video beam Epson', 'Epson', 'PowerLite X49', 'disponible', 'docente', '3.600 lúmenes, HDMI y VGA.'],
            ['Proyectores', 'PRO-002', 'Video beam Epson', 'Epson', 'PowerLite E20', 'disponible', 'docente', '3.400 lúmenes, HDMI.'],
            ['Proyectores', 'PRO-003', 'Video beam BenQ', 'BenQ', 'MW560', 'disponible', 'docente', '4.000 lúmenes, HDMI y VGA.'],
            ['Proyectores', 'PRO-004', 'Video beam ViewSonic', 'ViewSonic', 'PA503W', 'mantenimiento', 'docente', 'Cambio de lámpara pendiente.'],

            ['Cables', 'CAB-001', 'Cable HDMI 5 m', null, null, 'disponible', 'estudiante', null],
            ['Cables', 'CAB-002', 'Cable HDMI 10 m', null, null, 'disponible', 'ambos', null],
            ['Cables', 'CAB-003', 'Adaptador USB-C a HDMI', 'Ugreen', null, 'disponible', 'ambos', null],
            ['Cables', 'CAB-004', 'Adaptador HDMI a VGA', null, null, 'disponible', 'ambos', null],
            ['Cables', 'CAB-005', 'Extensión eléctrica 5 m', null, null, 'disponible', 'ambos', null],
            ['Cables', 'CAB-006', 'Multitoma de 6 tomas', null, null, 'mantenimiento', 'ambos', 'Interruptor dañado.'],

            ['Audio', 'AUD-001', 'Parlante activo JBL', 'JBL', 'EON 710', 'disponible', 'docente', 'Incluye cable de poder y cable XLR.'],
            ['Audio', 'AUD-002', 'Parlante portátil JBL', 'JBL', 'Charge 5', 'disponible', 'ambos', 'Bluetooth, batería de 20 horas.'],
            ['Audio', 'AUD-003', 'Micrófono inalámbrico Shure', 'Shure', 'BLX24/SM58', 'disponible', 'docente', 'Receptor y micrófono de mano.'],
            ['Audio', 'AUD-004', 'Micrófono de solapa Rode', 'Rode', 'Wireless GO II', 'disponible', 'docente', 'Dos transmisores y un receptor.'],

            ['Tabletas', 'TAB-001', 'Tableta Samsung Galaxy', 'Samsung', 'Galaxy Tab A9+', 'disponible', 'ambos', null],
            ['Tabletas', 'TAB-002', 'Tableta Samsung Galaxy', 'Samsung', 'Galaxy Tab A9+', 'disponible', 'ambos', null],
            ['Tabletas', 'TAB-003', 'Tableta Apple iPad', 'Apple', 'iPad (10.ª generación)', 'disponible', 'docente', null],

            ['Cámaras', 'CAM-001', 'Cámara Canon EOS', 'Canon', 'EOS Rebel T7', 'disponible', 'ambos', 'Lente 18-55 mm, batería y cargador.'],
            ['Cámaras', 'CAM-002', 'Cámara Sony', 'Sony', 'ZV-E10', 'mantenimiento', 'docente', 'Revisión del sensor.'],
            ['Cámaras', 'CAM-003', 'Trípode Manfrotto', 'Manfrotto', 'Compact Action', 'disponible', 'ambos', null],

            ['Periféricos', 'PER-001', 'Presentador inalámbrico Logitech', 'Logitech', 'R500s', 'disponible', 'ambos', null],
            ['Periféricos', 'PER-002', 'Mouse inalámbrico Logitech', 'Logitech', 'M185', 'disponible', 'estudiante', null],
            ['Periféricos', 'PER-003', 'Teclado Logitech', 'Logitech', 'K120', 'disponible', 'estudiante', null],
            ['Periféricos', 'PER-004', 'Cámara web Logitech', 'Logitech', 'C920', 'disponible', 'ambos', null],

            ['Salones', 'SAL-301', 'Salón 301', null, null, 'disponible', 'docente', 'Capacidad para 40 personas, video beam instalado.'],
            ['Salones', 'SAL-302', 'Salón 302', null, null, 'disponible', 'ambos', 'Capacidad para 35 personas.'],
            ['Salones', 'SAL-303', 'Salón 303', null, null, 'disponible', 'docente', 'Capacidad para 40 personas, video beam instalado.'],
            ['Salones', 'SAL-401', 'Salón 401', null, null, 'mantenimiento', 'docente', 'Adecuación del sistema de sonido.'],

            ['Laboratorios', 'LAB-201', 'Laboratorio de cómputo 201', null, null, 'disponible', 'docente', '25 equipos de escritorio.'],
            ['Laboratorios', 'LAB-202', 'Laboratorio de cómputo 202', null, null, 'disponible', 'docente', '30 equipos de escritorio.'],
        ];

        foreach ($equipos as [$categoria, $codigo, $nombre, $marca, $modelo, $estado, $solicitablePor, $descripcion]) {
            $equipo = Equipo::firstOrNew(['codigo_inventario' => $codigo]);
            $equipo->fill([
                'categoria_id' => Categoria::where('nombre', $categoria)->value('id'),
                'nombre' => $nombre,
                'marca' => $marca,
                'modelo' => $modelo,
                'solicitable_por' => $solicitablePor,
                'descripcion' => $descripcion,
            ]);

            if (! $equipo->exists) {
                $equipo->estado = $estado;
            }

            $equipo->save();
        }
    }
}
