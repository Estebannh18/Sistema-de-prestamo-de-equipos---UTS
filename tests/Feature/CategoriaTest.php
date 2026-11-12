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

        $this->post('/categorias', ['nombre' => 'Salones', 'descripcion' => 'Aulas'])->assertRedirect('/categorias');
        $categoria = Categoria::where('nombre', 'Salones')->firstOrFail();

        $this->put("/categorias/{$categoria->id}", ['nombre' => 'Aulas'])->assertRedirect('/categorias');
        $this->assertSame('Aulas', $categoria->refresh()->nombre);

        $this->delete("/categorias/{$categoria->id}")->assertRedirect('/categorias');
        $this->assertModelMissing($categoria);
    }

    public function test_el_nombre_es_obligatorio_y_unico(): void
    {
        $this->actingAs($this->administrador());
        Categoria::create(['nombre' => 'Salones']);

        $this->post('/categorias', ['nombre' => ''])->assertSessionHasErrors('nombre');
        $this->post('/categorias', ['nombre' => 'Salones'])->assertSessionHasErrors('nombre');

        $this->assertSame(1, Categoria::count());
    }

    public function test_el_listado_se_filtra_por_nombre(): void
    {
        Categoria::create(['nombre' => 'Salones']);
        Categoria::create(['nombre' => 'Portátiles']);

        $this->actingAs($this->administrador())->get('/categorias?buscar=salo')
            ->assertSee('Salones')->assertDontSee('Portátiles');
    }

    public function test_no_se_elimina_una_categoria_con_equipos(): void
    {
        $categoria = Categoria::create(['nombre' => 'Salones']);
        Equipo::create(['categoria_id' => $categoria->id, 'codigo_inventario' => 'SAL-301', 'nombre' => 'Salón 301']);

        $this->actingAs($this->administrador())->delete("/categorias/{$categoria->id}");

        $this->assertModelExists($categoria);
    }

    public function test_otros_roles_no_gestionan_categorias(): void
    {
        foreach (['funcionario', 'docente', 'estudiante'] as $rol) {
            $this->actingAs(User::factory()->create(['rol' => $rol]))
                ->post('/categorias', ['nombre' => 'Intrusa'])
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('categorias', ['nombre' => 'Intrusa']);
    }
}
