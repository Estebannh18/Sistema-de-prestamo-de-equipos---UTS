<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\Prestamo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReporteTest extends TestCase
{
    use RefreshDatabase;

    private Equipo $portatil;

    private Equipo $salon;

    private User $estudiante;

    protected function setUp(): void
    {
        parent::setUp();

        $portatiles = Categoria::create(['nombre' => 'Portátiles', 'tipo' => 'equipo']);
        $salones = Categoria::create(['nombre' => 'Salones', 'tipo' => 'espacio']);
        $this->portatil = Equipo::create(['categoria_id' => $portatiles->id, 'codigo_inventario' => 'POR-001', 'nombre' => 'Portátil Lenovo']);
        $this->salon = Equipo::create(['categoria_id' => $salones->id, 'codigo_inventario' => 'SAL-301', 'nombre' => 'Salón 301', 'solicitable_por' => 'docente']);
        $this->estudiante = User::factory()->create(['rol' => 'estudiante', 'name' => 'Laura Méndez']);
    }

    private function prestamo(Equipo $equipo, string $estado, string $fecha, array $datos = []): Prestamo
    {
        return Prestamo::factory()->create($datos + [
            'equipo_id' => $equipo->id,
            'solicitante_id' => $this->estudiante->id,
            'estado' => $estado,
            'fecha' => $fecha,
            'fecha_esperada_devolucion' => $fecha,
        ]);
    }

    private function rango(): string
    {
        return 'desde='.today()->subDays(10)->toDateString().'&hasta='.today()->addDays(10)->toDateString();
    }

    public function test_solo_el_administrador_y_el_funcionario_ven_y_exportan_el_reporte(): void
    {
        foreach (['administrador', 'funcionario'] as $rol) {
            $this->actingAs(User::factory()->create(['rol' => $rol]))->get('/reportes/prestamos')->assertOk();
        }

        foreach (['docente', 'estudiante'] as $rol) {
            $usuario = User::factory()->create(['rol' => $rol]);
            $this->actingAs($usuario)->get('/reportes/prestamos')->assertForbidden();
            $this->actingAs($usuario)->get('/reportes/prestamos/pdf')->assertForbidden();
        }
    }

    public function test_los_indicadores_agrupan_los_estados_y_separan_las_devoluciones_atrasadas(): void
    {
        // Martes 24 de noviembre de 2026, 10:00 a. m.
        $this->travelTo(Carbon::parse('2026-11-24 10:00:00'));
        $ayer = '2026-11-23';
        $hoy = '2026-11-24';
        $manana = '2026-11-25';

        $this->prestamo($this->portatil, 'entregado', $ayer, ['aprobacion' => 'automatica']);                               // atrasado
        $this->prestamo($this->salon, 'entregado', $hoy, ['aprobacion' => 'funcionario', 'hora_inicio' => '09:00:00', 'hora_fin' => '10:30:00']); // en préstamo
        $this->prestamo($this->portatil, 'aprobado', $manana, ['aprobacion' => 'funcionario', 'hora_inicio' => '09:00:00', 'hora_fin' => '10:30:00']);
        $this->prestamo($this->salon, 'devuelto', $ayer, ['aprobacion' => 'funcionario']);
        $this->prestamo($this->salon, 'no_recogido', $ayer, ['hora_inicio' => '09:00:00', 'hora_fin' => '10:30:00']);
        $this->prestamo($this->salon, 'rechazado', $manana);
        $this->prestamo($this->portatil, 'cancelado', $manana);
        $this->prestamo($this->salon, 'pendiente', $manana, ['hora_inicio' => '13:30:00', 'hora_fin' => '15:00:00']);
        // Fuera del periodo: no cuenta.
        $this->prestamo($this->portatil, 'devuelto', '2026-10-01');

        $respuesta = $this->actingAs(User::factory()->create(['rol' => 'funcionario']))
            ->get('/reportes/prestamos?desde=2026-11-14&hasta=2026-11-30&estado=atrasado');

        $indicadores = $respuesta->viewData('indicadores');
        $this->assertSame(8, $indicadores['total']);
        $this->assertSame([
            'pendiente' => 1, 'aprobado' => 1, 'entregado' => 1, 'atrasado' => 1,
            'devuelto' => 1, 'novedad' => 1, 'cerrado' => 2,
        ], $indicadores['estados']);
        $this->assertSame(1, $indicadores['automatica']);
        $this->assertSame(3, $indicadores['funcionario']);

        // El detalle sí respeta el estado elegido, y lo nombra.
        $this->assertSame(1, $respuesta->viewData('prestamos')->total());
        $respuesta->assertSeeText('Devolución atrasada');
    }

    public function test_el_reporte_se_filtra_por_tipo_y_por_lo_que_atendio_cada_funcionario(): void
    {
        $funcionario = User::factory()->create(['rol' => 'funcionario']);
        $otro = User::factory()->create(['rol' => 'funcionario']);
        $hoy = today()->toDateString();
        $this->prestamo($this->portatil, 'devuelto', $hoy, ['funcionario_id' => $funcionario->id]);
        $this->prestamo($this->salon, 'rechazado', $hoy, ['funcionario_id' => $otro->id]);

        $this->actingAs($funcionario);
        $this->assertSame(1, $this->get('/reportes/prestamos?'.$this->rango().'&tipo=espacio')->viewData('prestamos')->total());
        $this->get('/reportes/prestamos?'.$this->rango().'&mios=1')
            ->assertSee('POR-001')->assertDontSee('SAL-301');
    }

    public function test_los_rankings_ordenan_por_cantidad(): void
    {
        foreach (['07:30:00', '09:00:00', '10:30:00'] as $hora) {
            $this->prestamo($this->portatil, 'devuelto', today()->toDateString(), ['hora_inicio' => $hora, 'hora_fin' => $hora]);
        }
        $this->prestamo($this->salon, 'devuelto', today()->toDateString());

        $masPedidos = $this->actingAs(User::factory()->create(['rol' => 'administrador']))
            ->get('/reportes/prestamos?'.$this->rango())->viewData('masPedidos');

        $this->assertSame(['Portátil Lenovo', 'Salón 301'], $masPedidos->pluck('nombre')->all());
        $this->assertSame([3, 1], $masPedidos->pluck('total')->map(fn ($total) => (int) $total)->all());
    }

    public function test_las_fechas_invalidas_no_generan_el_reporte(): void
    {
        $this->actingAs(User::factory()->create(['rol' => 'administrador']));

        $this->get('/reportes/prestamos?desde=2026-11-20&hasta=2026-11-01')->assertSessionHasErrors('hasta');
        $this->get('/reportes/prestamos?desde=2025-01-01&hasta=2026-11-01')->assertSessionHasErrors('hasta');
        $this->get('/reportes/prestamos?estado=perdido')->assertSessionHasErrors('estado');
    }

    public function test_el_pdf_se_descarga_con_los_mismos_filtros(): void
    {
        $this->prestamo($this->portatil, 'devuelto', today()->toDateString());

        $respuesta = $this->actingAs(User::factory()->create(['rol' => 'funcionario']))
            ->get('/reportes/prestamos/pdf?'.$this->rango());

        $respuesta->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString(
            'reporte-prestamos-'.today()->subDays(10)->format('Ymd').'-'.today()->addDays(10)->format('Ymd').'.pdf',
            $respuesta->headers->get('content-disposition'),
        );
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
    }

    public function test_el_boton_exportar_lleva_los_filtros_de_la_vista(): void
    {
        $this->actingAs(User::factory()->create(['rol' => 'administrador']))
            ->get('/reportes/prestamos?'.$this->rango().'&tipo=espacio')
            ->assertSee(route('reportes.prestamos.pdf', [
                'desde' => today()->subDays(10)->toDateString(),
                'hasta' => today()->addDays(10)->toDateString(),
                'tipo' => 'espacio',
            ]));
    }
}
