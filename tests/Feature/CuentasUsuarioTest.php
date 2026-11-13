<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\Prestamo;
use App\Models\Programa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Tipo de documento, cuentas desactivadas y restablecimiento del 2FA.
 */
class CuentasUsuarioTest extends TestCase
{
    use RefreshDatabase;

    private function administrador(): User
    {
        return User::factory()->create(['rol' => 'administrador', 'name' => 'Admin Principal']);
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(array $cambios = []): array
    {
        return $cambios + [
            'name' => 'Persona Nueva',
            'email' => 'persona@prestamos.test',
            'tipo_documento' => 'CC',
            'documento' => '1098765432',
            'rol' => 'estudiante',
            'programa_id' => Programa::where('snies', 101596)->value('id'),
            'password' => 'claveSegura1',
            'password_confirmation' => 'claveSegura1',
        ];
    }

    private function prestamoActivo(User $solicitante): void
    {
        $categoria = Categoria::create(['nombre' => 'Portátiles']);
        $equipo = Equipo::create(['categoria_id' => $categoria->id, 'codigo_inventario' => 'POR-001', 'nombre' => 'Portátil']);
        Prestamo::create(['equipo_id' => $equipo->id, 'solicitante_id' => $solicitante->id, 'estado' => 'aprobado', 'fecha_esperada_devolucion' => today()]);
    }

    // ---------- Tipo de documento ----------

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function documentosValidos(): array
    {
        return [
            'cédula de 6 dígitos' => ['CC', '123456', 'estudiante'],
            'cédula de 10 dígitos' => ['CC', '1098765432', 'docente'],
            'tarjeta de identidad de 10 dígitos' => ['TI', '1005678901', 'estudiante'],
            'tarjeta de identidad de 11 dígitos' => ['TI', '10056789012', 'estudiante'],
            'cédula de extranjería' => ['CE', '654321', 'funcionario'],
            'pasaporte con letras' => ['PA', 'AB123456', 'docente'],
        ];
    }

    #[DataProvider('documentosValidos')]
    public function test_cada_tipo_acepta_su_formato(string $tipo, string $numero, string $rol): void
    {
        $this->actingAs($this->administrador())
            ->post('/usuarios', $this->datos(['tipo_documento' => $tipo, 'documento' => $numero, 'rol' => $rol]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['tipo_documento' => $tipo, 'documento' => $numero]);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function documentosInvalidos(): array
    {
        return [
            'cédula de 5 dígitos' => ['CC', '12345', 'documento'],
            'cédula de 11 dígitos' => ['CC', '10987654321', 'documento'],
            'cédula con letras' => ['CC', '10987A5432', 'documento'],
            'tarjeta de identidad de 9 dígitos' => ['TI', '100567890', 'documento'],
            'cédula de extranjería con letras' => ['CE', 'E123456', 'documento'],
            'pasaporte muy corto' => ['PA', 'AB12', 'documento'],
            'pasaporte con símbolos' => ['PA', 'AB#12345', 'documento'],
            'tipo inventado' => ['XX', '1098765432', 'tipo_documento'],
            'tipo vacío' => ['', '1098765432', 'tipo_documento'],
        ];
    }

    #[DataProvider('documentosInvalidos')]
    public function test_un_documento_que_no_cumple_su_tipo_no_se_guarda(string $tipo, string $numero, string $campo): void
    {
        $this->actingAs($this->administrador())
            ->post('/usuarios', $this->datos(['tipo_documento' => $tipo, 'documento' => $numero]))
            ->assertSessionHasErrors($campo);

        $this->assertDatabaseMissing('users', ['email' => 'persona@prestamos.test']);
    }

    public function test_el_pasaporte_se_guarda_en_mayusculas_y_sin_espacios(): void
    {
        $this->actingAs($this->administrador())
            ->post('/usuarios', $this->datos(['tipo_documento' => 'pa', 'documento' => 'ab 123-456', 'rol' => 'docente']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['tipo_documento' => 'PA', 'documento' => 'AB123456']);
    }

    public function test_la_tarjeta_de_identidad_solo_es_para_estudiantes(): void
    {
        $this->actingAs($this->administrador());

        foreach (['docente', 'funcionario', 'administrador'] as $rol) {
            $this->post('/usuarios', $this->datos(['tipo_documento' => 'TI', 'documento' => '1005678901', 'rol' => $rol]))
                ->assertSessionHasErrors(['tipo_documento' => 'La tarjeta de identidad solo se acepta para estudiantes.']);
        }
    }

    public function test_el_numero_no_se_repite_aunque_cambie_el_tipo(): void
    {
        User::factory()->create(['tipo_documento' => 'TI', 'documento' => '1005678901']);

        $this->actingAs($this->administrador())
            ->post('/usuarios', $this->datos(['tipo_documento' => 'CC', 'documento' => '1005678901']))
            ->assertSessionHasErrors(['documento' => 'Ya existe un usuario con ese número de documento.']);
    }

    // ---------- Desactivar y activar ----------

    public function test_una_cuenta_desactivada_no_puede_iniciar_sesion(): void
    {
        $usuario = User::factory()->create(['rol' => 'docente']);

        $this->actingAs($this->administrador())->patch("/usuarios/{$usuario->id}/desactivar")->assertSessionHas('status');
        $this->assertFalse($usuario->refresh()->activo);

        $this->post('/logout');
        $this->post('/login', ['email' => $usuario->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Tu cuenta está desactivada. Comunícate con el administrador.']);
        $this->assertGuest();
    }

    public function test_con_contrasena_incorrecta_no_se_revela_que_esta_desactivada(): void
    {
        $usuario = User::factory()->create(['activo' => false]);

        $this->post('/login', ['email' => $usuario->email, 'password' => 'incorrecta1'])
            ->assertSessionHasErrors(['email' => 'El correo o la contraseña no son correctos.']);
    }

    public function test_una_sesion_abierta_se_cierra_al_desactivar_la_cuenta(): void
    {
        $usuario = User::factory()->create(['rol' => 'estudiante']);
        $usuario->forceFill(['activo' => false])->save();

        $this->actingAs($usuario)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_al_desactivar_se_borran_sus_sesiones_guardadas(): void
    {
        config(['session.driver' => 'database']);
        $usuario = User::factory()->create(['rol' => 'funcionario']);
        DB::table('sessions')->insert(['id' => 'sesion-abierta', 'user_id' => $usuario->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($this->administrador())->patch("/usuarios/{$usuario->id}/desactivar");

        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-abierta']);
    }

    public function test_al_activarla_vuelve_a_entrar(): void
    {
        $usuario = User::factory()->create(['activo' => false]);

        $this->actingAs($this->administrador())->patch("/usuarios/{$usuario->id}/activar");
        $this->assertTrue($usuario->refresh()->activo);

        $this->post('/logout');
        $this->post('/login', ['email' => $usuario->email, 'password' => 'password'])->assertRedirect('/dashboard');
    }

    public function test_no_se_desactiva_la_propia_cuenta(): void
    {
        $admin = $this->administrador();
        User::factory()->create(['rol' => 'administrador']);

        $this->actingAs($admin)->patch("/usuarios/{$admin->id}/desactivar")->assertSessionHas('error');

        $this->assertTrue($admin->refresh()->activo);
    }

    public function test_no_se_desactiva_al_unico_administrador_activo(): void
    {
        $admin = $this->administrador();
        $otro = User::factory()->create(['rol' => 'administrador']);
        $otro->forceFill(['activo' => false])->save();
        $tercero = User::factory()->create(['rol' => 'administrador']);

        $this->actingAs($admin)->patch("/usuarios/{$tercero->id}/desactivar")->assertSessionHas('status');
        $this->assertFalse($tercero->refresh()->activo);

        // Solo queda $admin activo; un administrador inactivo no cuenta.
        $this->assertSame(1, User::administradoresActivos());
    }

    public function test_no_se_desactiva_con_prestamos_activos(): void
    {
        $usuario = User::factory()->create(['rol' => 'estudiante']);
        $this->prestamoActivo($usuario);

        $this->actingAs($this->administrador())->patch("/usuarios/{$usuario->id}/desactivar")->assertSessionHas('error');

        $this->assertTrue($usuario->refresh()->activo);
    }

    public function test_el_listado_filtra_por_estado(): void
    {
        User::factory()->create(['name' => 'Activa Uno']);
        User::factory()->create(['name' => 'Inactiva Dos', 'activo' => false]);

        $this->actingAs($this->administrador());
        $this->get('/usuarios?estado=inactivos')->assertSee('Inactiva Dos')->assertDontSee('Activa Uno');
        $this->get('/usuarios?estado=activos')->assertSee('Activa Uno')->assertDontSee('Inactiva Dos');
    }

    public function test_el_formulario_no_puede_reactivar_una_cuenta(): void
    {
        $usuario = User::factory()->create(['rol' => 'funcionario', 'activo' => false]);

        $this->actingAs($this->administrador())->put("/usuarios/{$usuario->id}", [
            'name' => $usuario->name, 'email' => $usuario->email, 'tipo_documento' => 'CC', 'documento' => $usuario->documento,
            'rol' => 'funcionario', 'activo' => 1, 'password' => '', 'password_confirmation' => '',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($usuario->refresh()->activo);
    }

    // ---------- Restablecer 2FA ----------

    public function test_el_administrador_restablece_el_2fa_de_otro_usuario(): void
    {
        $usuario = User::factory()->create(['rol' => 'docente']);
        $usuario->forceFill([
            'two_factor_secret' => encrypt('SECRETO'),
            'two_factor_recovery_codes' => encrypt('[]'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($this->administrador())->patch("/usuarios/{$usuario->id}/restablecer-2fa")->assertSessionHas('status');

        $usuario->refresh();
        $this->assertNull($usuario->two_factor_secret);
        $this->assertNull($usuario->two_factor_confirmed_at);

        $this->post('/logout');
        $this->post('/login', ['email' => $usuario->email, 'password' => 'password'])->assertRedirect('/dashboard');
    }

    public function test_el_administrador_no_restablece_su_propio_2fa_desde_aqui(): void
    {
        $admin = $this->administrador();
        $admin->forceFill(['two_factor_secret' => encrypt('SECRETO'), 'two_factor_confirmed_at' => now()])->save();

        $this->actingAs($admin)->patch("/usuarios/{$admin->id}/restablecer-2fa")->assertSessionHas('error');

        $this->assertNotNull($admin->refresh()->two_factor_secret);
    }

    public function test_otros_roles_no_pueden_desactivar_ni_restablecer(): void
    {
        $victima = User::factory()->create(['rol' => 'estudiante']);

        foreach (['funcionario', 'docente', 'estudiante'] as $rol) {
            $quien = User::factory()->create(['rol' => $rol]);
            $this->actingAs($quien)->patch("/usuarios/{$victima->id}/desactivar")->assertForbidden();
            $this->actingAs($quien)->patch("/usuarios/{$victima->id}/restablecer-2fa")->assertForbidden();
        }

        $this->assertTrue($victima->refresh()->activo);
    }
}
