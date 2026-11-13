<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\Prestamo;
use App\Models\Programa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GestionUsuariosTest extends TestCase
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
            'name' => 'Docente Nuevo',
            'email' => 'nuevo@prestamos.test',
            'tipo_documento' => 'CC',
            'documento' => '2000000001',
            'rol' => 'docente',
            'programa_id' => $this->sistemas(),
            'password' => 'claveSegura1',
            'password_confirmation' => 'claveSegura1',
        ];
    }


    /** Id de Ingeniería de Sistemas, sede Bucaramanga (SNIES 101596): los programas reales se cargan al migrar. */
    private function sistemas(): int
    {
        return Programa::where('snies', 101596)->value('id');
    }

    private function prestamo(User $solicitante, string $estado = 'pendiente'): Prestamo
    {
        $categoria = Categoria::firstOrCreate(['nombre' => 'Portátiles']);
        $equipo = Equipo::create(['categoria_id' => $categoria->id, 'codigo_inventario' => 'POR-'.fake()->unique()->numberBetween(100, 999), 'nombre' => 'Portátil']);

        return Prestamo::create(['equipo_id' => $equipo->id, 'solicitante_id' => $solicitante->id, 'estado' => $estado, 'fecha_esperada_devolucion' => today()]);
    }

    // ---------- Pantallas ----------

    public function test_los_formularios_de_crear_y_editar_se_muestran(): void
    {
        $admin = $this->administrador();
        $otro = User::factory()->create(['rol' => 'docente']);
        $otro->forceFill(['two_factor_secret' => encrypt('SECRETO'), 'two_factor_confirmed_at' => now()])->save();

        $this->actingAs($admin);
        $this->get('/usuarios/create')->assertOk()->assertSee('Datos personales')->assertSee('Elige un rol')->assertSee('(obligatorio)', false);
        $this->get("/usuarios/{$otro->id}/edit")->assertOk()->assertSee('Acciones de la cuenta')->assertSee('Restablecer 2FA');
        $this->get("/usuarios/{$admin->id}/edit")->assertOk()->assertSee('No puedes cambiar tu propio rol')->assertDontSee('Acciones de la cuenta');

        $otro->forceFill(['activo' => false])->save();
        $this->get("/usuarios/{$otro->id}/edit")->assertOk()->assertSee('Activar cuenta');
    }

    // ---------- Crear ----------

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

    public function test_los_datos_se_limpian_antes_de_guardar(): void
    {
        $this->actingAs($this->administrador())->post('/usuarios', $this->datos([
            'name' => '  María   José  Pérez ',
            'email' => '  Maria.Perez@Prestamos.TEST ',
            'documento' => '1.098.765.432',
            'telefono' => '(300) 123-4567',
            'rol' => 'funcionario',
            'dependencia' => '  Almacén   de  equipos ',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'name' => 'María José Pérez',
            'email' => 'maria.perez@prestamos.test',
            'documento' => '1098765432',
            'telefono' => '3001234567',
            'dependencia' => 'Almacén de equipos',
            'programa_id' => null,
        ]);
    }

    /**
     * Cada caso: campo, valor inválido.
     *
     * @return array<string, array{string, mixed}>
     */
    public static function datosInvalidos(): array
    {
        return [
            'nombre vacío' => ['name', ''],
            'nombre de una sola palabra' => ['name', 'Juan'],
            'nombre con números' => ['name', 'Juan Pérez 2'],
            'nombre con símbolos' => ['name', 'Juan @Pérez'],
            'nombre muy largo' => ['name', 'Juan '.str_repeat('a', 100)],
            'correo vacío' => ['email', ''],
            'correo sin arroba' => ['email', 'nuevo.prestamos.test'],
            'correo sin dominio' => ['email', 'nuevo@prestamos'],
            'correo con espacios' => ['email', 'nuevo uno@prestamos.test'],
            'documento vacío' => ['documento', ''],
            'documento con letras' => ['documento', '10987A5432'],
            'documento muy corto' => ['documento', '12345'],
            'documento muy largo' => ['documento', '123456789012'],
            'teléfono con letras' => ['telefono', '300abc4567'],
            'teléfono muy corto' => ['telefono', '12345'],
            'teléfono muy largo' => ['telefono', '30012345678'],
            'rol vacío' => ['rol', ''],
            'rol inventado' => ['rol', 'superadmin'],
            'programa vacío para docente' => ['programa_id', ''],
            'programa que no existe' => ['programa_id', 999999],
            'programa escrito como texto' => ['programa_id', 'Ingeniería de Sistemas'],
            'contraseña vacía' => ['password', ''],
            'contraseña corta' => ['password', 'abc12'],
            'contraseña sin números' => ['password', 'soloLetras'],
            'contraseña sin letras' => ['password', '12345678'],
            'contraseña muy larga' => ['password', str_repeat('a1', 37)],
        ];
    }

    #[DataProvider('datosInvalidos')]
    public function test_un_dato_invalido_no_crea_el_usuario(string $campo, mixed $valor): void
    {
        $datos = $this->datos([$campo => $valor]);
        if ($campo === 'password') {
            $datos['password_confirmation'] = $valor;
        }

        $this->actingAs($this->administrador())->post('/usuarios', $datos)->assertSessionHasErrors($campo);

        $this->assertDatabaseMissing('users', ['email' => 'nuevo@prestamos.test']);
    }

    public function test_un_nombre_invalido_muestra_un_solo_mensaje(): void
    {
        $this->actingAs($this->administrador())->post('/usuarios', $this->datos(['name' => 'Juan']));

        $this->assertSame(['Escribe nombre y apellido, solo con letras.'], session('errors')->get('name'));
    }

    public function test_las_contrasenas_deben_coincidir_y_ser_distintas_del_documento(): void
    {
        $this->actingAs($this->administrador());

        $this->post('/usuarios', $this->datos(['password_confirmation' => 'otraClave9']))->assertSessionHasErrors('password');
        $this->post('/usuarios', $this->datos(['tipo_documento' => 'PA', 'documento' => 'AB123456', 'password' => 'AB123456', 'password_confirmation' => 'AB123456']))->assertSessionHasErrors('password');
        $this->post('/usuarios', $this->datos(['documento' => '1234567', 'password' => 'clave1234567', 'password_confirmation' => 'clave1234567']))->assertSessionHasNoErrors();
    }

    public function test_el_programa_es_obligatorio_solo_para_estudiantes_y_docentes(): void
    {
        $this->actingAs($this->administrador());

        $this->post('/usuarios', $this->datos(['rol' => 'estudiante', 'programa_id' => '']))
            ->assertSessionHasErrors(['programa_id' => 'Elige el programa académico: es obligatorio para estudiantes y docentes.']);
        $this->post('/usuarios', $this->datos(['rol' => 'docente', 'programa_id' => '']))->assertSessionHasErrors('programa_id');
        $this->post('/usuarios', $this->datos(['rol' => 'funcionario', 'programa_id' => '', 'dependencia' => '']))->assertSessionHasNoErrors();
    }

    public function test_correo_y_documento_no_se_repiten(): void
    {
        User::factory()->create(['email' => 'nuevo@prestamos.test', 'documento' => '9999999999']);
        $this->actingAs($this->administrador());

        $this->post('/usuarios', $this->datos(['email' => 'NUEVO@prestamos.test', 'documento' => '2000000001']))->assertSessionHasErrors('email');
        $this->post('/usuarios', $this->datos(['email' => 'otro@prestamos.test', 'documento' => '9.999.999.999']))->assertSessionHasErrors('documento');
    }

    public function test_los_errores_se_muestran_en_espanol(): void
    {
        $this->actingAs($this->administrador())
            ->from('/usuarios/create')
            ->post('/usuarios', $this->datos(['documento' => 'abc', 'password' => 'corta', 'password_confirmation' => 'corta']))
            ->assertSessionHasErrors([
                'documento' => 'La cédula de ciudadanía solo tiene números, entre 6 y 10 dígitos.',
                'password' => 'La contraseña debe tener al menos 8 caracteres.',
            ]);
    }

    // ---------- Programa académico ----------

    public function test_los_59_programas_oficiales_se_cargan_al_migrar_y_el_seeder_no_los_duplica(): void
    {
        $this->assertSame(59, Programa::count());

        $this->seed(\Database\Seeders\ProgramaSeeder::class);

        $this->assertSame(59, Programa::count());
        // La misma carrera en varias sedes son programas distintos (cada uno con su SNIES).
        $this->assertSame(4, Programa::where('nombre', 'Administración de Empresas')->count());
        $this->assertSame(5, Programa::distinct()->count('sede'));
        $this->assertDatabaseHas('programas', ['snies' => 119105, 'nombre' => 'Ingeniería en Inteligencia Artificial y Ciencia de Datos', 'sede' => 'Campus Virtual', 'modalidad' => 'virtual', 'nivel' => 'universitario']);
    }

    public function test_el_formulario_lista_los_programas_agrupados_por_sede(): void
    {
        $respuesta = $this->actingAs($this->administrador())->get('/usuarios/create')->assertOk();

        $respuesta->assertSee('Programa académico')
            ->assertSee('<optgroup label="Sede Bucaramanga (presencial)">', false)
            ->assertSee('<optgroup label="Campus Virtual (virtual)">', false)
            ->assertSee('Tecnología en Desarrollo de Sistemas Informáticos')
            ->assertSee('Ingeniería en Energías')
            ->assertSee('Dependencia');
        preg_match('/<select id="programa_id".*?<\/select>/s', $respuesta->getContent(), $select);
        $this->assertSame(59, preg_match_all('/<option value="\d+"/', $select[0]), 'El select debe ofrecer los 59 programas.');
        $this->assertSame(5, substr_count($select[0], '<optgroup'));
    }

    public function test_el_estudiante_queda_guardado_con_su_programa(): void
    {
        $this->actingAs($this->administrador())->post('/usuarios', $this->datos([
            'rol' => 'estudiante', 'programa_id' => $this->sistemas(), 'dependencia' => 'No debería guardarse',
        ]))->assertSessionHasNoErrors();

        $usuario = User::where('email', 'nuevo@prestamos.test')->first();
        $this->assertSame('Ingeniería de Sistemas', $usuario->programa->nombre);
        $this->assertSame('Bucaramanga', $usuario->programa->sede);
        $this->assertNull($usuario->dependencia);
    }

    public function test_el_personal_guarda_dependencia_y_no_programa(): void
    {
        $this->actingAs($this->administrador())->post('/usuarios', $this->datos([
            'rol' => 'funcionario', 'programa_id' => $this->sistemas(), 'dependencia' => 'Almacén',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'nuevo@prestamos.test', 'programa_id' => null, 'dependencia' => 'Almacén']);

        $this->post('/usuarios', $this->datos(['email' => 'otro@prestamos.test', 'documento' => '2000000002', 'rol' => 'funcionario', 'dependencia' => 'AB']))
            ->assertSessionHasErrors(['dependencia' => 'La dependencia debe tener al menos 3 caracteres.']);
    }

    public function test_al_pasar_de_estudiante_a_funcionario_se_quita_el_programa(): void
    {
        $estudiante = User::factory()->create(['rol' => 'estudiante', 'programa_id' => $this->sistemas()]);

        $this->actingAs($this->administrador())->put("/usuarios/{$estudiante->id}", [
            'name' => $estudiante->name, 'email' => $estudiante->email, 'tipo_documento' => 'CC', 'documento' => $estudiante->documento,
            'rol' => 'funcionario', 'programa_id' => $this->sistemas(), 'dependencia' => 'Laboratorios', 'password' => '', 'password_confirmation' => '',
        ])->assertSessionHasNoErrors();

        $estudiante->refresh();
        $this->assertNull($estudiante->programa_id);
        $this->assertSame('Laboratorios', $estudiante->dependencia);
    }

    public function test_la_edicion_muestra_elegido_el_programa_del_usuario(): void
    {
        $estudiante = User::factory()->create(['rol' => 'estudiante', 'programa_id' => $this->sistemas()]);

        $this->actingAs($this->administrador())->get("/usuarios/{$estudiante->id}/edit")
            ->assertOk()
            ->assertSee('<option value="'.$this->sistemas().'" selected>Ingeniería de Sistemas</option>', false);
    }

    public function test_la_lista_muestra_el_programa_con_su_sede_y_filtra_por_programa(): void
    {
        $virtual = Programa::where('snies', 107432)->value('id'); // Administración de Empresas, virtual
        User::factory()->create(['name' => 'Ana Sistemas', 'rol' => 'estudiante', 'programa_id' => $this->sistemas()]);
        User::factory()->create(['name' => 'Beto Virtual', 'rol' => 'estudiante', 'programa_id' => $virtual]);
        User::factory()->create(['name' => 'Carla Almacén', 'rol' => 'funcionario', 'dependencia' => 'Almacén']);
        $this->actingAs($this->administrador());

        $this->get('/usuarios')
            ->assertSeeInOrder(['Ana Sistemas', 'Ingeniería de Sistemas', 'Sede Bucaramanga (presencial)'])
            ->assertSeeInOrder(['Beto Virtual', 'Administración de Empresas', 'Campus Virtual (virtual)'])
            ->assertSeeInOrder(['Carla Almacén', 'Almacén']);

        $this->get("/usuarios?programa={$virtual}")->assertSee('Beto Virtual')->assertDontSee('Ana Sistemas')->assertDontSee('Carla Almacén');
        $this->get('/usuarios?programa=abc')->assertOk()->assertSee('Ana Sistemas');
    }

    public function test_no_se_puede_borrar_un_programa_que_tiene_usuarios(): void
    {
        User::factory()->create(['rol' => 'estudiante', 'programa_id' => $this->sistemas()]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Programa::find($this->sistemas())->delete();
    }

    // ---------- Editar ----------

    public function test_editar_sin_contrasena_conserva_la_actual(): void
    {
        $usuario = User::factory()->create(['rol' => 'estudiante', 'programa_id' => $this->sistemas()]);
        $hash = $usuario->password;

        $this->actingAs($this->administrador())->put("/usuarios/{$usuario->id}", [
            'name' => 'Nombre Editado',
            'email' => $usuario->email,
            'tipo_documento' => 'CC',
            'documento' => $usuario->documento,
            'rol' => 'docente',
            'programa_id' => $this->sistemas(),
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect('/usuarios');

        $usuario->refresh();
        $this->assertSame('Nombre Editado', $usuario->name);
        $this->assertSame('docente', $usuario->rol);
        $this->assertSame($hash, $usuario->password);
    }

    public function test_el_administrador_no_puede_cambiar_su_propio_rol(): void
    {
        $admin = $this->administrador();
        User::factory()->create(['rol' => 'administrador']);

        $this->actingAs($admin)->put("/usuarios/{$admin->id}", $this->datos([
            'name' => $admin->name, 'email' => $admin->email, 'tipo_documento' => 'CC', 'documento' => $admin->documento, 'rol' => 'estudiante',
            'password' => '', 'password_confirmation' => '',
        ]))->assertSessionHasErrors('rol');

        $this->assertSame('administrador', $admin->refresh()->rol);
    }

    public function test_el_administrador_si_puede_editar_sus_demas_datos(): void
    {
        $admin = $this->administrador();

        $this->actingAs($admin)->put("/usuarios/{$admin->id}", [
            'name' => 'Admin Renombrado', 'email' => $admin->email, 'tipo_documento' => 'CC', 'documento' => $admin->documento, 'rol' => 'administrador',
            'dependencia' => 'Dependencia de préstamos', 'password' => '', 'password_confirmation' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Admin Renombrado', $admin->refresh()->name);
    }

    public function test_no_se_cambia_el_rol_con_prestamos_activos(): void
    {
        $estudiante = User::factory()->create(['rol' => 'estudiante', 'programa_id' => $this->sistemas()]);
        $this->prestamo($estudiante, 'aprobado');

        $this->actingAs($this->administrador())->put("/usuarios/{$estudiante->id}", [
            'name' => $estudiante->name, 'email' => $estudiante->email, 'tipo_documento' => 'CC', 'documento' => $estudiante->documento, 'rol' => 'docente',
            'programa_id' => $this->sistemas(), 'password' => '', 'password_confirmation' => '',
        ])->assertSessionHasErrors('rol');

        $this->assertSame('estudiante', $estudiante->refresh()->rol);
    }

    public function test_con_prestamos_ya_devueltos_si_se_cambia_el_rol(): void
    {
        $estudiante = User::factory()->create(['rol' => 'estudiante', 'programa_id' => $this->sistemas()]);
        $this->prestamo($estudiante, 'devuelto');

        $this->actingAs($this->administrador())->put("/usuarios/{$estudiante->id}", [
            'name' => $estudiante->name, 'email' => $estudiante->email, 'tipo_documento' => 'CC', 'documento' => $estudiante->documento, 'rol' => 'docente',
            'programa_id' => $this->sistemas(), 'password' => '', 'password_confirmation' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame('docente', $estudiante->refresh()->rol);
    }

    public function test_cambiar_la_contrasena_de_otro_cierra_sus_sesiones(): void
    {
        // En las pruebas las sesiones viven en memoria; aquí se simula el driver real del proyecto (database).
        config(['session.driver' => 'database']);
        $usuario = User::factory()->create(['rol' => 'funcionario']);
        DB::table('sessions')->insert(['id' => 'sesion-de-prueba', 'user_id' => $usuario->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($this->administrador())->put("/usuarios/{$usuario->id}", [
            'name' => $usuario->name, 'email' => $usuario->email, 'tipo_documento' => 'CC', 'documento' => $usuario->documento, 'rol' => 'funcionario',
            'password' => 'nuevaClave9', 'password_confirmation' => 'nuevaClave9',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-de-prueba']);
        $this->assertTrue(Hash::check('nuevaClave9', $usuario->refresh()->password));
    }

    // ---------- Eliminar ----------

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
        $this->prestamo($usuario, 'devuelto');

        $this->actingAs($this->administrador())->delete("/usuarios/{$usuario->id}");

        $this->assertModelExists($usuario);
    }

    // ---------- Listado ----------

    public function test_el_listado_busca_filtra_y_pagina(): void
    {
        $admin = $this->administrador();
        User::factory()->create(['name' => 'Laura Gómez', 'rol' => 'estudiante', 'documento' => '1111111111']);
        User::factory()->create(['name' => 'Pedro Ruiz', 'rol' => 'docente', 'documento' => '2222222222']);
        User::factory()->count(12)->create(['rol' => 'funcionario']);

        $this->actingAs($admin);
        $this->get('/usuarios?buscar=laura')->assertSee('Laura Gómez')->assertDontSee('Pedro Ruiz');
        $this->get('/usuarios?buscar=2.222.222.222')->assertSee('Pedro Ruiz')->assertDontSee('Laura Gómez');
        $this->get('/usuarios?rol=docente')->assertSee('Pedro Ruiz')->assertDontSee('Laura Gómez');
        $this->get('/usuarios')->assertSee('15 usuarios')->assertSee('Página 1 de 2');
    }

    // ---------- Permisos ----------

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
        $user = User::factory()->create(['rol' => 'estudiante', 'name' => 'Ana Torres']);

        $this->actingAs($user)->patch('/profile', ['name' => 'Ana Torres', 'email' => $user->email, 'rol' => 'administrador', 'documento' => '1']);

        $user->refresh();
        $this->assertSame('estudiante', $user->rol);
        $this->assertNotSame('1', $user->documento);
    }

    public function test_el_perfil_usa_las_mismas_reglas_de_nombre_y_correo(): void
    {
        $user = User::factory()->create(['name' => 'Ana Torres']);

        $this->actingAs($user)->patch('/profile', ['name' => 'Ana', 'email' => 'correo-malo'])->assertSessionHasErrors(['name', 'email']);
        $this->actingAs($user)->patch('/profile', ['name' => ' Ana  María Torres ', 'email' => ' ANA@Prestamos.TEST '])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Ana María Torres', $user->name);
        $this->assertSame('ana@prestamos.test', $user->email);
    }
}
