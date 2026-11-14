<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoriaTest extends TestCase
{
    use RefreshDatabase;

    private function administrador(): User
    {
        return User::factory()->create(['rol' => 'administrador']);
    }

    public function test_el_administrador_crea_edita_y_elimina_una_categoria(): void
    {
        $this->actingAs($this->administrador());

        $this->post('/categorias', ['nombre' => 'Salones', 'descripcion' => 'Aulas', 'tipo' => 'espacio', 'icono' => 'salon'])->assertRedirect('/categorias');
        $categoria = Categoria::where('nombre', 'Salones')->firstOrFail();
        $this->assertTrue($categoria->esEspacio());
        $this->assertSame('salon', $categoria->icono);

        $this->put("/categorias/{$categoria->id}", ['nombre' => 'Aulas', 'tipo' => 'espacio'])->assertRedirect('/categorias');
        $this->assertSame('Aulas', $categoria->refresh()->nombre);

        $this->delete("/categorias/{$categoria->id}")->assertRedirect('/categorias');
        $this->assertModelMissing($categoria);
    }

    public function test_el_nombre_es_obligatorio_y_unico(): void
    {
        $this->actingAs($this->administrador());
        Categoria::create(['nombre' => 'Salones']);

        $this->post('/categorias', ['nombre' => '', 'tipo' => 'equipo'])->assertSessionHasErrors('nombre');
        $this->post('/categorias', ['nombre' => 'Salones', 'tipo' => 'equipo'])->assertSessionHasErrors('nombre');

        $this->assertSame(1, Categoria::count());
    }

    public function test_el_tipo_y_el_icono_solo_aceptan_valores_de_la_lista(): void
    {
        $this->actingAs($this->administrador());

        $this->post('/categorias', ['nombre' => 'Drones'])->assertSessionHasErrors('tipo');
        $this->post('/categorias', ['nombre' => 'Drones', 'tipo' => 'vehiculo'])->assertSessionHasErrors('tipo');
        $this->post('/categorias', ['nombre' => 'Drones', 'tipo' => 'equipo', 'icono' => 'cohete'])->assertSessionHasErrors('icono');

        $this->assertDatabaseMissing('categorias', ['nombre' => 'Drones']);
    }

    public function test_el_listado_se_filtra_por_nombre(): void
    {
        Categoria::create(['nombre' => 'Salones']);
        Categoria::create(['nombre' => 'Portátiles']);

        $this->actingAs($this->administrador())->get('/categorias?buscar=salo')
            ->assertSee('Salones')->assertDontSee('Portátiles');
    }

    public function test_el_listado_agrupa_equipos_y_espacios(): void
    {
        Categoria::create(['nombre' => 'Portátiles', 'tipo' => 'equipo']);
        Categoria::create(['nombre' => 'Laboratorios', 'tipo' => 'espacio']);

        $this->actingAs($this->administrador())->get('/categorias')
            ->assertSeeInOrder(['Equipos', 'Portátiles', 'Espacios', 'Laboratorios']);
    }

    public function test_cada_categoria_muestra_cuantos_equipos_hay_en_cada_estado(): void
    {
        $categoria = Categoria::create(['nombre' => 'Proyectores']);
        foreach (['disponible', 'disponible', 'prestado', 'mantenimiento'] as $i => $estado) {
            Equipo::create(['categoria_id' => $categoria->id, 'codigo_inventario' => "PRO-00{$i}", 'nombre' => "Video beam {$i}", 'estado' => $estado]);
        }

        $this->actingAs($this->administrador())->get('/categorias')
            ->assertSeeText('4 equipos')
            ->assertSeeText('2 disponibles')
            ->assertSeeText('1 prestado')
            ->assertSeeText('1 en mantenimiento');
    }

    public function test_no_se_elimina_una_categoria_con_equipos(): void
    {
        $categoria = Categoria::create(['nombre' => 'Salones']);
        Equipo::create(['categoria_id' => $categoria->id, 'codigo_inventario' => 'SAL-301', 'nombre' => 'Salón 301']);

        $this->actingAs($this->administrador())->delete("/categorias/{$categoria->id}");

        $this->assertModelExists($categoria);
    }

    public function test_el_boton_eliminar_solo_aparece_si_la_categoria_no_tiene_equipos(): void
    {
        $conEquipos = Categoria::create(['nombre' => 'Cables']);
        $vacia = Categoria::create(['nombre' => 'Drones']);
        Equipo::create(['categoria_id' => $conEquipos->id, 'codigo_inventario' => 'CAB-001', 'nombre' => 'Cable HDMI']);

        $this->actingAs($this->administrador())->get('/categorias')
            ->assertSee('action="'.route('categorias.destroy', $vacia).'"', false)
            ->assertDontSee('action="'.route('categorias.destroy', $conEquipos).'"', false)
            ->assertSee('No se puede eliminar Cables porque tiene equipos');
    }

    public function test_otros_roles_no_gestionan_categorias(): void
    {
        foreach (['funcionario', 'docente', 'estudiante'] as $rol) {
            $this->actingAs(User::factory()->create(['rol' => $rol]))
                ->post('/categorias', ['nombre' => 'Intrusa', 'tipo' => 'equipo'])
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('categorias', ['nombre' => 'Intrusa']);
    }
}
