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
        $this->salones = Categoria::create(['nombre' => 'Salones', 'tipo' => 'espacio']);
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

    public function test_crear_salon_usa_la_categoria_de_tipo_espacio_aunque_cambie_de_nombre(): void
    {
        $this->salones->update(['nombre' => 'Aulas y auditorios']);
        Categoria::create(['nombre' => 'Portátiles', 'tipo' => 'equipo']);

        $this->actingAs($this->administrador())->get('/equipos')
            ->assertSee('Crear salón')
            ->assertSee(route('equipos.create', ['categoria' => $this->salones->id]), false);
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

    public function test_el_listado_se_filtra_por_tipo_y_se_busca_por_marca(): void
    {
        $cables = Categoria::create(['nombre' => 'Cables', 'tipo' => 'equipo']);
        $this->salon();
        $this->salon(['categoria_id' => $cables->id, 'codigo_inventario' => 'CAB-003', 'nombre' => 'Adaptador USB-C a HDMI', 'marca' => 'Ugreen']);

        $this->actingAs($this->administrador());
        $this->get('/equipos?tipo=espacio')->assertSee('SAL-301')->assertDontSee('CAB-003');
        $this->get('/equipos?tipo=equipo')->assertSee('CAB-003')->assertDontSee('SAL-301');
        $this->get('/equipos?buscar=ugreen')->assertSee('CAB-003')->assertDontSee('SAL-301');
    }

    public function test_el_personal_ve_cuantos_hay_en_cada_estado_y_los_agrupa_por_categoria(): void
    {
        $cables = Categoria::create(['nombre' => 'Cables']);
        $this->salon();
        $this->salon(['codigo_inventario' => 'SAL-302', 'nombre' => 'Salón 302', 'estado' => 'prestado']);
        $this->salon(['categoria_id' => $cables->id, 'codigo_inventario' => 'CAB-001', 'nombre' => 'Cable HDMI', 'estado' => 'mantenimiento']);

        $respuesta = $this->actingAs(User::factory()->create(['rol' => 'funcionario']))->get('/equipos?estado=prestado');

        // Los totales no dependen del estado elegido: así se ve cuántos hay de cada uno.
        $respuesta->assertSeeTextInOrder(['Disponibles', '1', 'Prestados', '1', 'En mantenimiento', '1'])
            ->assertSee('SAL-302')->assertDontSee('SAL-301')
            ->assertSeeText('1 resultado');

        $this->get('/equipos')->assertSeeInOrder(['Cables', 'CAB-001', 'Salones', 'SAL-301']);
    }

    public function test_docentes_y_estudiantes_solicitan_desde_el_listado_y_no_ven_acciones_del_personal(): void
    {
        $salon = $this->salon(['solicitable_por' => 'ambos']);

        $this->actingAs(User::factory()->create(['rol' => 'docente']))->get('/equipos')
            ->assertSee(route('prestamos.create', ['equipo' => $salon->id]), false)
            ->assertDontSee('En mantenimiento')
            ->assertDontSee(route('equipos.edit', $salon), false);

        $this->actingAs($this->administrador())->get('/equipos')
            ->assertDontSee(route('prestamos.create', ['equipo' => $salon->id]), false)
            ->assertSee(route('equipos.edit', $salon), false);
    }

    public function test_el_boton_eliminar_se_deshabilita_si_el_equipo_tiene_prestamos(): void
    {
        $conPrestamo = $this->salon();
        $libre = $this->salon(['codigo_inventario' => 'SAL-302', 'nombre' => 'Salón 302']);
        Prestamo::create(['equipo_id' => $conPrestamo->id, 'solicitante_id' => User::factory()->create()->id, 'fecha_esperada_devolucion' => today()]);

        $this->actingAs($this->administrador())->get('/equipos')
            ->assertSee('action="'.route('equipos.destroy', $libre).'"', false)
            ->assertDontSee('action="'.route('equipos.destroy', $conPrestamo).'"', false)
            ->assertSee('No se puede eliminar Salón 301 porque tiene préstamos');
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
