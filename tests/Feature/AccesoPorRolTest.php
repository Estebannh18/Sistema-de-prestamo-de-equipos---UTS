<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccesoPorRolTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Cada ruta con el código que debe recibir cada rol.
     *
     * @return array<string, array{string, string, int}>
     */
    public static function accesos(): array
    {
        $matriz = [
            '/usuarios' => ['administrador' => 200, 'funcionario' => 403, 'docente' => 403, 'estudiante' => 403],
            '/categorias' => ['administrador' => 200, 'funcionario' => 403, 'docente' => 403, 'estudiante' => 403],
            '/categorias/create' => ['administrador' => 200, 'funcionario' => 403, 'docente' => 403, 'estudiante' => 403],
            '/equipos' => ['administrador' => 200, 'funcionario' => 200, 'docente' => 200, 'estudiante' => 200],
            '/equipos/create' => ['administrador' => 200, 'funcionario' => 403, 'docente' => 403, 'estudiante' => 403],
            '/prestamos' => ['administrador' => 200, 'funcionario' => 200, 'docente' => 200, 'estudiante' => 200],
            '/prestamos/create' => ['administrador' => 403, 'funcionario' => 403, 'docente' => 200, 'estudiante' => 200],
            '/reportes/prestamos' => ['administrador' => 200, 'funcionario' => 200, 'docente' => 403, 'estudiante' => 403],
            '/seguridad/2fa' => ['administrador' => 200, 'funcionario' => 200, 'docente' => 200, 'estudiante' => 200],
        ];

        $casos = [];
        foreach ($matriz as $ruta => $roles) {
            foreach ($roles as $rol => $codigo) {
                $casos["$rol en $ruta"] = [$rol, $ruta, $codigo];
            }
        }

        return $casos;
    }

    #[DataProvider('accesos')]
    public function test_cada_rol_recibe_el_codigo_esperado(string $rol, string $ruta, int $codigo): void
    {
        $user = User::factory()->create(['rol' => $rol]);

        $this->actingAs($user)->get($ruta)->assertStatus($codigo);
    }

    public function test_sin_sesion_las_rutas_redirigen_al_login(): void
    {
        foreach (['/dashboard', '/usuarios', '/equipos', '/prestamos', '/seguridad/2fa'] as $ruta) {
            $this->get($ruta)->assertRedirect('/login');
        }
    }

    public function test_no_hay_registro_publico(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_cada_rol_ve_solo_sus_opciones_en_el_menu(): void
    {
        $administrador = User::factory()->create(['rol' => 'administrador']);
        $docente = User::factory()->create(['rol' => 'docente']);

        $this->actingAs($administrador)->get('/dashboard')->assertSee('Usuarios')->assertDontSee('Solicitud');
        $this->actingAs($docente)->get('/dashboard')->assertSee('Solicitud')->assertDontSee('Usuarios');
    }

    public function test_cada_equipo_lo_pide_solo_el_rol_habilitado(): void
    {
        $categoria = Categoria::create(['nombre' => 'Salones']);
        $soloDocente = Equipo::create(['categoria_id' => $categoria->id, 'codigo_inventario' => 'SAL-301', 'nombre' => 'Salón 301', 'solicitable_por' => 'docente']);
        $ambos = Equipo::create(['categoria_id' => $categoria->id, 'codigo_inventario' => 'SAL-302', 'nombre' => 'Salón 302', 'solicitable_por' => 'ambos']);
        $soloEstudiante = Equipo::create(['categoria_id' => $categoria->id, 'codigo_inventario' => 'SAL-303', 'nombre' => 'Salón 303', 'solicitable_por' => 'estudiante']);

        $docente = User::factory()->create(['rol' => 'docente']);
        $estudiante = User::factory()->create(['rol' => 'estudiante']);
        $funcionario = User::factory()->create(['rol' => 'funcionario']);

        $this->assertTrue($soloDocente->permiteSolicitud($docente));
        $this->assertFalse($soloDocente->permiteSolicitud($estudiante));
        $this->assertTrue($ambos->permiteSolicitud($docente));
        $this->assertTrue($ambos->permiteSolicitud($estudiante));
        $this->assertFalse($ambos->permiteSolicitud($funcionario));
        $this->assertTrue($soloEstudiante->permiteSolicitud($estudiante));

        $this->actingAs($docente)->get('/prestamos/create')
            ->assertSee('SAL-301')->assertSee('SAL-302')->assertDontSee('SAL-303');
        $this->actingAs($estudiante)->get('/prestamos/create')
            ->assertDontSee('SAL-301')->assertSee('SAL-302')->assertSee('SAL-303');
    }
}
