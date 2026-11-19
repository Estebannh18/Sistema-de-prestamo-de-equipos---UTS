<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\Programa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Espacios (salones, laboratorios): se crean con su propio formulario, solo los piden docentes
 * y pueden tener carreras recomendadas.
 */
class EspacioTest extends TestCase
{
    use RefreshDatabase;

    private Categoria $laboratorios;

    private Categoria $portatiles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->laboratorios = Categoria::create(['nombre' => 'Laboratorios', 'tipo' => 'espacio']);
        $this->portatiles = Categoria::create(['nombre' => 'Portátiles', 'tipo' => 'equipo']);
    }

    private function administrador(): User
    {
        return User::factory()->create(['rol' => 'administrador']);
    }

    private function laboratorioIa(): Equipo
    {
        $sistemas = Programa::factory()->create(['nombre' => 'Ingeniería de Sistemas']);
        $laboratorio = Equipo::create([
            'categoria_id' => $this->laboratorios->id,
            'codigo_inventario' => 'LAB-301',
            'nombre' => 'Laboratorio de Inteligencia Artificial',
            'solicitable_por' => 'docente',
        ]);
        $laboratorio->programas()->attach($sistemas);

        return $laboratorio;
    }

    public function test_el_formulario_de_espacios_pide_ubicacion_capacidad_y_carreras_y_no_marca(): void
    {
        Programa::factory()->create(['nombre' => 'Ingeniería Ambiental']);
        $this->actingAs($this->administrador());

        $this->get("/equipos/create?categoria={$this->laboratorios->id}")
            ->assertSeeText('Crear espacio')
            ->assertSee('name="ubicacion"', false)
            ->assertSee('name="capacidad"', false)
            ->assertSee('Ingeniería Ambiental')
            ->assertDontSee('name="marca"', false)
            ->assertDontSee('name="solicitable_por"', false)
            // Solo se ofrecen categorías de espacios.
            ->assertDontSee('<option value="'.$this->portatiles->id.'"', false);

        $this->get('/equipos/create')
            ->assertSeeText('Crear equipo')
            ->assertSee('name="marca"', false)
            ->assertSee('name="solicitable_por"', false)
            ->assertDontSee('name="capacidad"', false)
            ->assertDontSee('<option value="'.$this->laboratorios->id.'"', false);
    }

    public function test_el_administrador_crea_un_espacio_con_carreras_habilitadas_en_todas_sus_sedes(): void
    {
        // Un nombre que no está en la lista oficial que carga la migración, para controlar cuántas sedes hay.
        $bucaramanga = Programa::factory()->create(['nombre' => 'Ingeniería de Prueba', 'sede' => 'Bucaramanga']);
        $virtual = Programa::factory()->create(['nombre' => 'Ingeniería de Prueba', 'sede' => 'Campus Virtual']);

        $this->actingAs($this->administrador())->post('/equipos', [
            'categoria_id' => $this->laboratorios->id,
            'codigo_inventario' => 'LAB-301',
            'nombre' => 'Laboratorio de Inteligencia Artificial',
            'ubicacion' => 'Edificio B, piso 3',
            'capacidad' => 24,
            'estado' => 'disponible',
            'carreras' => ['Ingeniería de Prueba'],
            // Lo que no corresponde a un espacio se ignora.
            'marca' => 'Dell',
            'solicitable_por' => 'estudiante',
        ])->assertRedirect('/equipos');

        $laboratorio = Equipo::where('codigo_inventario', 'LAB-301')->firstOrFail();
        // Con carreras lo piden los docentes y los estudiantes de esas carreras.
        $this->assertSame('ambos', $laboratorio->solicitable_por);
        $this->assertNull($laboratorio->marca);
        $this->assertSame(24, $laboratorio->capacidad);
        $this->assertEqualsCanonicalizing([$bucaramanga->id, $virtual->id], $laboratorio->programas->pluck('id')->all());
    }

    public function test_un_equipo_no_guarda_datos_de_espacio(): void
    {
        Programa::factory()->create(['nombre' => 'Ingeniería de Sistemas']);

        $this->actingAs($this->administrador())->post('/equipos', [
            'categoria_id' => $this->portatiles->id,
            'codigo_inventario' => 'POR-010',
            'nombre' => 'Portátil Dell',
            'marca' => 'Dell',
            'estado' => 'disponible',
            'solicitable_por' => 'ambos',
            'capacidad' => 30,
            'carreras' => ['Ingeniería de Sistemas'],
        ])->assertRedirect('/equipos');

        $portatil = Equipo::where('codigo_inventario', 'POR-010')->firstOrFail();
        $this->assertSame('Dell', $portatil->marca);
        $this->assertSame('ambos', $portatil->solicitable_por);
        $this->assertNull($portatil->capacidad);
        $this->assertCount(0, $portatil->programas);
    }

    public function test_solo_se_recomiendan_carreras_que_existen(): void
    {
        $this->actingAs($this->administrador())->post('/equipos', [
            'categoria_id' => $this->laboratorios->id,
            'codigo_inventario' => 'LAB-399',
            'nombre' => 'Laboratorio inventado',
            'estado' => 'disponible',
            'carreras' => ['Carrera que no existe'],
        ])->assertSessionHasErrors('carreras.0');

        $this->assertDatabaseMissing('equipos', ['codigo_inventario' => 'LAB-399']);
    }

    public function test_el_docente_ve_todos_los_espacios_y_cuales_son_de_su_carrera(): void
    {
        $laboratorio = $this->laboratorioIa();
        $deSistemas = User::factory()->create(['rol' => 'docente', 'programa_id' => $laboratorio->programas->first()->id]);
        $deOtroPrograma = User::factory()->create(['rol' => 'docente', 'programa_id' => Programa::factory()->create()->id]);

        $this->actingAs($deSistemas)->get('/equipos')
            ->assertSee('Laboratorio de Inteligencia Artificial')
            ->assertSeeText('De tu carrera')
            ->assertSee('Ingeniería de Sistemas');

        // Para los docentes la carrera no restringe: otro docente también lo ve y lo puede pedir.
        $this->actingAs($deOtroPrograma)->get('/equipos')
            ->assertSee('Laboratorio de Inteligencia Artificial')
            ->assertDontSeeText('De tu carrera')
            ->assertSee(route('prestamos.create', ['equipo' => $laboratorio->id]), false);
    }

    public function test_la_busqueda_encuentra_espacios_por_carrera(): void
    {
        $this->laboratorioIa();

        $this->actingAs($this->administrador())->get('/equipos?buscar=sistemas')
            ->assertSee('LAB-301');
    }

    public function test_el_estudiante_solo_ve_los_espacios_habilitados_para_su_carrera(): void
    {
        $laboratorio = $this->laboratorioIa();
        $sinCarreras = Equipo::create(['categoria_id' => $this->laboratorios->id, 'codigo_inventario' => 'LAB-999', 'nombre' => 'Sala sin carreras', 'solicitable_por' => 'docente']);
        $deSistemas = User::factory()->create(['rol' => 'estudiante', 'programa_id' => $laboratorio->programas->first()->id]);
        $deOtra = User::factory()->create(['rol' => 'estudiante', 'programa_id' => Programa::factory()->create()->id]);

        $this->actingAs($deSistemas)->get('/equipos')
            ->assertSee('LAB-301')->assertDontSee('LAB-999')
            ->assertSee('aria-label="Tipo"', false);
        $this->assertTrue($laboratorio->permiteSolicitud($deSistemas));

        $this->actingAs($deOtra)->get('/equipos')->assertDontSee('LAB-301')->assertDontSee('LAB-999');
        $this->assertFalse($laboratorio->permiteSolicitud($deOtra));
        $this->actingAs($deOtra)->get("/equipos/{$laboratorio->id}")->assertForbidden();

        // Un espacio sin carreras solo lo piden docentes.
        $this->assertFalse($sinCarreras->permiteSolicitud($deSistemas));
        $this->assertTrue($sinCarreras->permiteSolicitud(User::factory()->create(['rol' => 'docente'])));
    }
}
