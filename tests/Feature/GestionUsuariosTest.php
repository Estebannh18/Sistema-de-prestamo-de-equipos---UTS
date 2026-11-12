<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\Prestamo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GestionUsuariosTest extends TestCase
{
    use RefreshDatabase;

    private function administrador(): User
    {
        return User::factory()->create(['rol' => 'administrador']);
    }

    /**
     * @return array<string, string>
     */
    private function datos(array $cambios = []): array
    {
        return $cambios + [
            'name' => 'Docente Nuevo',
            'email' => 'nuevo@prestamos.test',
            'documento' => '2000000001',
            'rol' => 'docente',
            'password' => 'claveSegura1',
            'password_confirmation' => 'claveSegura1',
        ];
    }

    public function test_el_administrador_crea_un_usuario_que_puede_iniciar_sesion(): void
    {
        $this->actingAs($this->administrador())->post('/usuarios', $this->datos())->assertRedirect('/usuarios');

        $nuevo = User::where('email', 'nuevo@prestamos.test')->firstOrFail();
        $this->assertSame('docente', $nuevo->rol);
        $this->assertTrue(Hash::check('claveSegura1', $nuevo->password));

        $this->post('/logout');
        $this->post('/login', ['email' => $nuevo->email, 'password' => 'claveSegura1'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($nuevo);
    }

    public function test_los_datos_invalidos_no_crean_el_usuario(): void
    {
        $this->actingAs($this->administrador());

        $this->post('/usuarios', $this->datos(['email' => 'no-es-correo']))->assertSessionHasErrors('email');
        $this->post('/usuarios', $this->datos(['rol' => 'superadmin']))->assertSessionHasErrors('rol');
        $this->post('/usuarios', $this->datos(['password_confirmation' => 'otra']))->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['documento' => '2000000001']);
    }

    public function test_editar_sin_contrasena_conserva_la_actual(): void
    {
        $usuario = User::factory()->create();
        $hash = $usuario->password;

        $this->actingAs($this->administrador())->put("/usuarios/{$usuario->id}", [
            'name' => 'Nombre Editado',
            'email' => $usuario->email,
            'documento' => $usuario->documento,
            'rol' => 'docente',
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect('/usuarios');

        $usuario->refresh();
        $this->assertSame('Nombre Editado', $usuario->name);
        $this->assertSame('docente', $usuario->rol);
        $this->assertSame($hash, $usuario->password);
    }

    public function test_el_administrador_elimina_un_usuario_sin_prestamos(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($this->administrador())->delete("/usuarios/{$usuario->id}")->assertRedirect('/usuarios');

        $this->assertModelMissing($usuario);
    }

    public function test_no_se_elimina_la_cuenta_propia(): void
    {
        $administrador = $this->administrador();

        $this->actingAs($administrador)->delete("/usuarios/{$administrador->id}");

        $this->assertModelExists($administrador);
    }

    public function test_no_se_elimina_un_usuario_con_prestamos(): void
    {
        $usuario = User::factory()->create();
        $categoria = Categoria::create(['nombre' => 'Portátiles']);
        $equipo = Equipo::create(['categoria_id' => $categoria->id, 'codigo_inventario' => 'POR-001', 'nombre' => 'Portátil 1']);
        Prestamo::create(['equipo_id' => $equipo->id, 'solicitante_id' => $usuario->id, 'fecha_esperada_devolucion' => today()]);

        $this->actingAs($this->administrador())->delete("/usuarios/{$usuario->id}");

        $this->assertModelExists($usuario);
    }

    public function test_otros_roles_no_gestionan_usuarios(): void
    {
        foreach (['funcionario', 'docente', 'estudiante'] as $rol) {
            $this->actingAs(User::factory()->create(['rol' => $rol]))
                ->post('/usuarios', $this->datos())
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('users', ['documento' => '2000000001']);
    }

    public function test_nadie_cambia_su_propio_rol_desde_el_perfil(): void
    {
        $user = User::factory()->create(['rol' => 'estudiante']);

        $this->actingAs($user)->patch('/profile', ['name' => $user->name, 'email' => $user->email, 'rol' => 'administrador']);

        $this->assertSame('estudiante', $user->refresh()->rol);
    }
}
