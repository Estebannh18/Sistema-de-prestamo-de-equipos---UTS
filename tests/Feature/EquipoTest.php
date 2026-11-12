<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\Prestamo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipoTest extends TestCase
{
    use RefreshDatabase;

    private Categoria $salones;

    protected function setUp(): void
    {
        parent::setUp();
        $this->salones = Categoria::create(['nombre' => 'Salones']);
    }

    private function administrador(): User
    {
        return User::factory()->create(['rol' => 'administrador']);
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(array $cambios = []): array
    {
        return $cambios + [
            'categoria_id' => $this->salones->id,
            'codigo_inventario' => 'SAL-301',
            'nombre' => 'Salón 301',
            'estado' => 'disponible',
            'solicitable_por' => 'docente',
        ];
    }

    private function salon(array $cambios = []): Equipo
    {
        return Equipo::create($this->datos($cambios));
    }

    public function test_el_administrador_crea_un_salon_solo_para_docentes(): void
    {
        $this->actingAs($this->administrador())->post('/equipos', $this->datos())->assertRedirect('/equipos');

        $salon = Equipo::where('codigo_inventario', 'SAL-301')->firstOrFail();
        $this->assertSame('docente', $salon->solicitable_por);
        $this->assertTrue($salon->categoria->is($this->salones));
    }

    public function test_crear_salon_llega_con_la_categoria_elegida(): void
    {
        $this->actingAs($this->administrador())->get("/equipos/create?categoria={$this->salones->id}")
            ->assertOk()
            ->assertSee('<option value="'.$this->salones->id.'" selected', false);
    }

    public function test_los_datos_invalidos_no_crean_el_equipo(): void
    {
        $this->actingAs($this->administrador());
        $this->salon();

        $this->post('/equipos', $this->datos())->assertSessionHasErrors('codigo_inventario');
        $this->post('/equipos', $this->datos(['codigo_inventario' => 'X', 'categoria_id' => 999]))->assertSessionHasErrors('categoria_id');
        $this->post('/equipos', $this->datos(['codigo_inventario' => 'X', 'solicitable_por' => 'nadie']))->assertSessionHasErrors('solicitable_por');
        $this->post('/equipos', $this->datos(['codigo_inventario' => 'X', 'estado' => 'prestado']))->assertSessionHasErrors('estado');

        $this->assertSame(1, Equipo::count());
    }

    public function test_el_listado_se_filtra_por_nombre_categoria_y_estado(): void
    {
        $otra = Categoria::create(['nombre' => 'Cables']);
        $this->salon();
        $this->salon(['categoria_id' => $otra->id, 'codigo_inventario' => 'CAB-001', 'nombre' => 'Cable HDMI', 'estado' => 'mantenimiento']);

        $this->actingAs($this->administrador());
        $this->get('/equipos?buscar=hdmi')->assertSee('CAB-001')->assertDontSee('SAL-301');
        $this->get("/equipos?categoria={$this->salones->id}")->assertSee('SAL-301')->assertDontSee('CAB-001');
        $this->get('/equipos?estado=mantenimiento')->assertSee('CAB-001')->assertDontSee('SAL-301');
    }

    public function test_el_estado_de_un_equipo_prestado_no_se_cambia_a_mano(): void
    {
        $salon = $this->salon(['estado' => 'prestado']);

        $this->actingAs($this->administrador())->put("/equipos/{$salon->id}", $this->datos(['estado' => 'disponible', 'nombre' => 'Salón editado']));

        $salon->refresh();
        $this->assertSame('prestado', $salon->estado);
        $this->assertSame('Salón editado', $salon->nombre);
    }

    public function test_no_se_elimina_un_equipo_con_prestamos(): void
    {
        $salon = $this->salon();
        Prestamo::create(['equipo_id' => $salon->id, 'solicitante_id' => User::factory()->create()->id, 'fecha_esperada_devolucion' => today()]);

        $this->actingAs($this->administrador())->delete("/equipos/{$salon->id}");

        $this->assertModelExists($salon);
    }

    public function test_el_administrador_elimina_un_equipo_sin_prestamos(): void
    {
        $salon = $this->salon();

        $this->actingAs($this->administrador())->delete("/equipos/{$salon->id}")->assertRedirect('/equipos');

        $this->assertModelMissing($salon);
    }

    public function test_el_estudiante_no_abre_por_url_un_salon_solo_para_docentes(): void
    {
        $salon = $this->salon();

        $this->actingAs(User::factory()->create(['rol' => 'estudiante']))->get("/equipos/{$salon->id}")->assertForbidden();
        $this->actingAs(User::factory()->create(['rol' => 'docente']))->get("/equipos/{$salon->id}")->assertOk();
        $this->actingAs(User::factory()->create(['rol' => 'funcionario']))->get("/equipos/{$salon->id}")->assertOk();
    }

    public function test_otros_roles_no_crean_equipos(): void
    {
        foreach (['funcionario', 'docente', 'estudiante'] as $rol) {
            $this->actingAs(User::factory()->create(['rol' => $rol]))->post('/equipos', $this->datos())->assertForbidden();
        }

        $this->assertSame(0, Equipo::count());
    }
}
