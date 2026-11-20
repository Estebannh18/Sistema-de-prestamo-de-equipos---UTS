<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Configuracion;
use App\Models\Equipo;
use App\Models\Horario;
use App\Models\Prestamo;
use App\Models\Programa;
use App\Models\User;
use App\Notifications\SolicitudCancelada;
use App\Notifications\SolicitudPorAprobar;
use App\Notifications\SolicitudRespondida;
use App\Services\PrestamoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Reglas de las solicitudes de estudiantes (plan-modulo-estudiantes.html).
 * El reloj se fija el lunes 23/11/2026 a las 8:00 a. m.; «mañana» es el martes 24/11.
 */
class SolicitudesTest extends TestCase
{
    use RefreshDatabase;

    private const MANANA = '2026-11-24';

    private Equipo $videoBeam;

    private Equipo $videoBeam2;

    private Equipo $salon;

    private int $sistemas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-23 08:00:00'));

        $this->sistemas = Programa::where('snies', 101596)->value('id'); // Ingeniería de Sistemas, Bucaramanga
        $equipos = Categoria::create(['nombre' => 'Video beams', 'tipo' => 'equipo']);
        $espacios = Categoria::create(['nombre' => 'Salones', 'tipo' => 'espacio']);

        $this->videoBeam = $this->conHorario(Equipo::create(['categoria_id' => $equipos->id, 'codigo_inventario' => 'VB-01', 'nombre' => 'Video beam verde', 'solicitable_por' => 'ambos']));
        $this->videoBeam2 = $this->conHorario(Equipo::create(['categoria_id' => $equipos->id, 'codigo_inventario' => 'VB-02', 'nombre' => 'Video beam azul', 'solicitable_por' => 'ambos']));
        $this->salon = $this->conHorario(Equipo::create(['categoria_id' => $espacios->id, 'codigo_inventario' => 'SAL-304B', 'nombre' => 'Salón 304B', 'solicitable_por' => 'ambos']));
        $this->salon->programas()->attach($this->sistemas);
    }

    private function conHorario(Equipo $equipo): Equipo
    {
        foreach (Horario::DIAS as $dia => $nombre) {
            foreach (Horario::BLOQUES_INSTITUCIONALES as [$inicio, $fin]) {
                $equipo->horarios()->create(['dia_semana' => $dia, 'hora_inicio' => $inicio, 'hora_fin' => $fin]);
            }
        }

        return $equipo;
    }

    private function bloque(Equipo $equipo, string $inicio = '09:00', string $fecha = self::MANANA): Horario
    {
        return $equipo->horarios()->where('dia_semana', Carbon::parse($fecha)->dayOfWeekIso)->where('hora_inicio', "{$inicio}:00")->firstOrFail();
    }

    private function estudiante(?int $programa = null): User
    {
        return User::factory()->create(['rol' => 'estudiante', 'programa_id' => $programa ?? $this->sistemas]);
    }

    /** @return array<string, mixed> */
    private function datos(Equipo $equipo, string $inicio = '09:00', string $fecha = self::MANANA, array $extra = []): array
    {
        return $extra + ['equipo_id' => $equipo->id, 'fecha' => $fecha, 'horario_id' => $this->bloque($equipo, $inicio, $fecha)->id];
    }

    private function prestamoAprobado(User $user, Equipo $equipo, string $inicio = '09:00', string $fecha = self::MANANA): Prestamo
    {
        $horario = $this->bloque($equipo, $inicio, $fecha);

        return Prestamo::factory()->create([
            'equipo_id' => $equipo->id, 'solicitante_id' => $user->id, 'estado' => 'aprobado', 'aprobacion' => 'automatica',
            'fecha' => $fecha, 'fecha_esperada_devolucion' => $fecha, 'hora_inicio' => $horario->hora_inicio, 'hora_fin' => $horario->hora_fin,
        ]);
    }

    // ---------- Equipos: aprobación automática ----------

    public function test_un_equipo_libre_queda_aprobado_de_una_vez_sin_avisar_al_funcionario(): void
    {
        $funcionario = User::factory()->create(['rol' => 'funcionario']);

        $this->actingAs($this->estudiante())->post('/prestamos', $this->datos($this->videoBeam))
            ->assertRedirect('/prestamos')->assertSessionHas('status', fn ($m) => str_contains($m, 'quedó aprobado'));

        $prestamo = Prestamo::firstOrFail();
        $this->assertSame('aprobado', $prestamo->estado);
        $this->assertSame('automatica', $prestamo->aprobacion);
        $this->assertSame('09:00:00', $prestamo->hora_inicio);
        $this->assertSame('10:30:00', $prestamo->hora_fin);
        $this->assertSame(0, $funcionario->notifications()->count());
    }

    public function test_con_el_maximo_de_equipos_aprobados_se_avisa_antes_y_va_a_aprobacion(): void
    {
        $estudiante = $this->estudiante();
        $this->prestamoAprobado($estudiante, $this->videoBeam);
        $funcionario = User::factory()->create(['rol' => 'funcionario']);
        $this->actingAs($estudiante);

        // Primero vuelve con el aviso, sin crear nada.
        $datos = $this->datos($this->videoBeam2);
        $this->post('/prestamos', $datos)
            ->assertRedirect(route('prestamos.create', ['equipo' => $this->videoBeam2->id, 'fecha' => self::MANANA, 'horario' => $datos['horario_id']]))
            ->assertSessionHas('confirmar');
        $this->assertSame(1, Prestamo::count());

        $this->get(route('prestamos.create', ['equipo' => $this->videoBeam2->id, 'fecha' => self::MANANA, 'horario' => $datos['horario_id']]))
            ->assertOk()->assertSee('Ya tienes un equipo aprobado')->assertSee('Video beam verde')
            ->assertSee('Continuar y enviar a aprobación')->assertSee('Cancelar «Video beam verde»');

        // Si confirma, queda pendiente y se le avisa al funcionario.
        $this->post('/prestamos', $datos + ['confirmado' => 1])->assertRedirect('/prestamos');
        $nueva = Prestamo::where('equipo_id', $this->videoBeam2->id)->firstOrFail();
        $this->assertSame('pendiente', $nueva->estado);
        $this->assertSame(1, $funcionario->notifications()->where('type', SolicitudPorAprobar::class)->count());
    }

    public function test_un_prestamo_que_ya_paso_no_cuenta_para_el_maximo(): void
    {
        $estudiante = $this->estudiante();
        $this->prestamoAprobado($estudiante, $this->videoBeam, '06:00', '2026-11-23'); // hoy de 6:00 a 7:30: ya terminó

        $this->actingAs($estudiante)->post('/prestamos', $this->datos($this->videoBeam2))->assertRedirect('/prestamos');
        $this->assertSame('aprobado', Prestamo::where('equipo_id', $this->videoBeam2->id)->value('estado'));
    }

    public function test_cancelar_el_aprobado_desde_el_aviso_permite_pedir_el_otro_aprobado(): void
    {
        $estudiante = $this->estudiante();
        $verde = $this->prestamoAprobado($estudiante, $this->videoBeam);
        $datos = $this->datos($this->videoBeam2);
        $this->actingAs($estudiante);

        $this->patch("/prestamos/{$verde->id}/cancelar", ['volver' => ['equipo' => $this->videoBeam2->id, 'fecha' => self::MANANA, 'horario' => $datos['horario_id']]])
            ->assertRedirect(route('prestamos.create', ['equipo' => $this->videoBeam2->id, 'fecha' => self::MANANA, 'horario' => $datos['horario_id']]));
        $this->assertSame('cancelado', $verde->refresh()->estado);

        $this->post('/prestamos', $datos)->assertRedirect('/prestamos');
        $this->assertSame('aprobado', Prestamo::where('equipo_id', $this->videoBeam2->id)->value('estado'));
    }

    // ---------- Espacios: siempre a aprobación para estudiantes ----------

    public function test_un_espacio_queda_pendiente_y_se_avisa_solo_a_los_funcionarios(): void
    {
        $funcionarios = User::factory()->count(2)->create(['rol' => 'funcionario']);
        $inactivo = User::factory()->create(['rol' => 'funcionario', 'activo' => false]);
        $admin = User::factory()->create(['rol' => 'administrador']);
        $otroEstudiante = $this->estudiante();

        $this->actingAs($this->estudiante())->post('/prestamos', $this->datos($this->salon))
            ->assertRedirect('/prestamos')->assertSessionHas('status', fn ($m) => str_contains($m, 'pendiente de aprobación'));

        $this->assertSame('pendiente', Prestamo::firstOrFail()->estado);
        foreach ($funcionarios as $funcionario) {
            $aviso = $funcionario->notifications()->firstOrFail();
            $this->assertSame('Nueva solicitud de espacio', $aviso->data['title']);
            $this->assertStringContainsString('Ingeniería de Sistemas', $aviso->data['message']);
            $this->assertStringContainsString('Salón 304B', $aviso->data['message']);
        }
        $this->assertSame(0, $inactivo->notifications()->count());
        $this->assertSame(0, $admin->notifications()->count());
        $this->assertSame(0, $otroEstudiante->notifications()->count());
    }

    public function test_con_un_espacio_en_curso_se_avisa_y_lista_los_que_tiene(): void
    {
        $estudiante = $this->estudiante();
        $this->actingAs($estudiante)->post('/prestamos', $this->datos($this->salon, '09:00'));

        $datos = $this->datos($this->salon, '13:30');
        $this->post('/prestamos', $datos)->assertSessionHas('confirmar');
        $this->get(route('prestamos.create', ['equipo' => $this->salon->id, 'fecha' => self::MANANA, 'horario' => $datos['horario_id']]))
            ->assertSee('Ya tienes una solicitud de espacio en curso')
            ->assertSee('Pendiente de aprobación')
            ->assertSee('también pasará a');

        $this->post('/prestamos', $datos + ['confirmado' => 1]);
        $this->assertSame(2, Prestamo::where('estado', 'pendiente')->count());
    }

    public function test_el_estudiante_no_pide_espacios_de_otra_carrera(): void
    {
        $deOtra = $this->estudiante(Programa::where('snies', 53456)->value('id')); // Administración de Empresas

        $this->actingAs($deOtra)->get(route('prestamos.create', ['equipo' => $this->salon->id]))->assertForbidden();
        $this->post('/prestamos', $this->datos($this->salon))
            ->assertSessionHasErrors(['solicitud' => 'No puedes solicitar el espacio «Salón 304B».']);
        // En las tarjetas no aparece (el nombre sí sale en el aviso del intento anterior).
        $this->get(route('prestamos.create', ['fecha' => self::MANANA]))->assertSee('Todavía no hay espacios habilitados para tu carrera');
        $this->get(route('prestamos.create', ['fecha' => self::MANANA, 'tipo' => 'espacio', 'categoria' => 'todas']))->assertDontSee('SAL-304B');
        $this->assertSame(0, Prestamo::count());
    }

    // ---------- Disponibilidad ----------

    public function test_un_bloque_aprobado_para_otro_aparece_ocupado_y_no_se_puede_pedir(): void
    {
        $this->prestamoAprobado($this->estudiante(), $this->videoBeam);
        $estudiante = $this->estudiante();

        $this->actingAs($estudiante)->get(route('prestamos.create', ['equipo' => $this->videoBeam->id, 'fecha' => self::MANANA]))
            ->assertSee('Ocupado')->assertSee('Libre');
        $this->post('/prestamos', $this->datos($this->videoBeam))
            ->assertSessionHasErrors(['solicitud' => 'Ese bloque ya está ocupado. Elige otro horario.']);
    }

    public function test_no_se_pide_dos_veces_lo_mismo_en_el_mismo_bloque(): void
    {
        $estudiante = $this->estudiante();
        $this->actingAs($estudiante)->post('/prestamos', $this->datos($this->salon));

        $this->post('/prestamos', $this->datos($this->salon, extra: ['confirmado' => 1]))
            ->assertSessionHasErrors(['solicitud' => 'Ya tienes una solicitud de «Salón 304B» en ese bloque.']);
    }

    public function test_reglas_de_fecha_hora_y_anticipacion(): void
    {
        $this->actingAs($this->estudiante());

        // Hoy a las 9:00: faltan 60 minutos y los equipos se piden con 2 h de anticipación.
        $this->post('/prestamos', $this->datos($this->videoBeam, '09:00', '2026-11-23'))
            ->assertSessionHasErrors(['solicitud' => 'Los equipos se deben solicitar con al menos 2 h de anticipación.']);
        // Hoy a las 6:00: ya pasó.
        $this->post('/prestamos', $this->datos($this->videoBeam, '06:00', '2026-11-23'))
            ->assertSessionHasErrors(['solicitud' => 'Ese bloque ya empezó o ya pasó. Elige otro.']);
        // Los espacios de estudiantes se piden con 24 h: mañana a las 7:30 faltan menos de 24 h.
        $this->post('/prestamos', $this->datos($this->salon, '07:30'))
            ->assertSessionHasErrors(['solicitud' => 'Los espacios se deben solicitar con al menos 24 h de anticipación.']);
        // Un bloque del lunes no sirve para el domingo.
        $this->post('/prestamos', ['equipo_id' => $this->videoBeam->id, 'fecha' => '2026-11-29', 'horario_id' => $this->bloque($this->videoBeam, '09:00', '2026-11-30')->id])
            ->assertSessionHasErrors(['solicitud' => 'Ese bloque no está en el horario de ese día. Elige uno de la lista.']);
        // Más de 14 días adelante.
        $this->post('/prestamos', $this->datos($this->videoBeam, '09:00', '2026-12-08'))
            ->assertSessionHasErrors(['solicitud' => 'Solo se puede solicitar hasta 14 días adelante.']);

        $this->assertSame(0, Prestamo::count());
    }

    public function test_la_anticipacion_sale_de_la_configuracion(): void
    {
        Configuracion::where('clave', 'anticipacion_equipos_estudiante')->update(['valor' => 30]);

        $this->actingAs($this->estudiante())->post('/prestamos', $this->datos($this->videoBeam, '09:00', '2026-11-23'))->assertSessionHasNoErrors();
        $this->assertSame(1, Prestamo::count());
    }

    public function test_un_equipo_en_mantenimiento_no_se_pide(): void
    {
        $this->videoBeam->mantenimientos()->create(['desde' => self::MANANA, 'hasta' => self::MANANA, 'motivo' => 'Cambio de lámpara.']);

        $this->actingAs($this->estudiante())->post('/prestamos', $this->datos($this->videoBeam))
            ->assertSessionHasErrors(['solicitud' => 'El equipo «Video beam verde» está en mantenimiento ese día. Motivo: Cambio de lámpara.']);
    }

    public function test_la_pantalla_muestra_solo_dias_de_lunes_a_sabado(): void
    {
        $fechas = app(PrestamoService::class)->fechasSolicitables();

        $this->assertFalse($fechas->contains(fn ($f) => $f->isSunday()));
        $this->assertSame('2026-11-23', $fechas->first()->toDateString());
        $this->assertSame('2026-12-07', $fechas->last()->toDateString());
    }

    // ---------- Aprobar, rechazar y choques ----------

    public function test_aprobar_rechaza_solas_las_que_se_cruzan_y_avisa_a_todos(): void
    {
        $funcionarios = User::factory()->count(2)->create(['rol' => 'funcionario']);
        $ana = $this->estudiante();
        $beto = $this->estudiante();

        $this->actingAs($ana)->post('/prestamos', $this->datos($this->salon));
        $this->travel(1)->hours();
        $this->actingAs($beto)->post('/prestamos', $this->datos($this->salon));
        [$deAna, $deBeto] = Prestamo::orderBy('id')->get()->all();
        $this->assertSame(2, $funcionarios[1]->notifications()->count());

        // Al revisar la de Beto, el funcionario ve la de Ana y cuándo la creó.
        $this->actingAs($funcionarios[0])->get("/prestamos/{$deBeto->id}")->assertOk()
            ->assertSee('Hay otra solicitud')
            ->assertSee($ana->name)
            ->assertSee('lunes 23/11/2026 a las 8:00')
            ->assertSee('antes que esta')
            ->assertSee('la otra pendiente se rechazará');

        $this->patch("/prestamos/{$deBeto->id}/aprobar")->assertRedirect('/prestamos/pendientes')
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'Se rechazaron automáticamente 1'));

        $this->assertSame('aprobado', $deBeto->refresh()->estado);
        $this->assertSame('funcionario', $deBeto->aprobacion);
        $this->assertSame($funcionarios[0]->id, $deBeto->funcionario_id);
        $this->assertSame('rechazado', $deAna->refresh()->estado);
        $this->assertSame(PrestamoService::MOTIVO_AUTOMATICO, $deAna->motivo_rechazo);

        // Los avisos «por aprobar» desaparecen para todos los funcionarios.
        $this->assertSame(0, DatabaseNotification::where('type', SolicitudPorAprobar::class)->count());
        // Los dos estudiantes reciben su respuesta.
        $this->assertSame('Solicitud aprobada', $beto->notifications()->firstOrFail()->data['title']);
        $this->assertStringContainsString('ya hay una solicitud aprobada para esa fecha y hora', $ana->notifications()->firstOrFail()->data['message']);
    }

    public function test_una_aprobacion_automatica_no_rechaza_las_pendientes_de_otros(): void
    {
        $conMaximo = $this->estudiante();
        $this->prestamoAprobado($conMaximo, $this->videoBeam2);
        $this->actingAs($conMaximo)->post('/prestamos', $this->datos($this->videoBeam, extra: ['confirmado' => 1]));
        $pendiente = Prestamo::where('equipo_id', $this->videoBeam->id)->firstOrFail();
        $this->assertSame('pendiente', $pendiente->estado);

        // Otro estudiante, sin préstamos, pide el mismo equipo y bloque: se aprueba solo, pero la pendiente sigue: decide el funcionario.
        $otro = User::factory()->create(['rol' => 'estudiante', 'programa_id' => $this->sistemas, 'name' => 'Beto Ruiz']);
        $this->actingAs($otro)->post('/prestamos', $this->datos($this->videoBeam));
        $this->assertSame('pendiente', $pendiente->refresh()->estado);

        // Para aprobarla, primero hay que cancelar la aprobada.
        $this->actingAs(User::factory()->create(['rol' => 'funcionario']))->patch("/prestamos/{$pendiente->id}/aprobar")
            ->assertSessionHasErrors(['solicitud' => 'Primero debes cancelar la solicitud aprobada de Beto Ruiz (estudiante) para el martes 24/11/2026, de 9:00 a. m. – 10:30 a. m..']);
        $this->assertSame('pendiente', $pendiente->refresh()->estado);
    }

    public function test_rechazar_pide_motivo_y_se_lo_avisa_al_estudiante(): void
    {
        $estudiante = $this->estudiante();
        $this->actingAs($estudiante)->post('/prestamos', $this->datos($this->salon));
        $prestamo = Prestamo::firstOrFail();
        $funcionario = User::factory()->create(['rol' => 'funcionario']);

        $this->actingAs($funcionario)->patch("/prestamos/{$prestamo->id}/rechazar", ['motivo' => ' '])
            ->assertSessionHasErrors(['motivo' => 'Escribe el motivo del rechazo: el estudiante lo verá en su notificación.']);
        $this->patch("/prestamos/{$prestamo->id}/rechazar", ['motivo' => 'Hay una actividad institucional en el salón.'])->assertRedirect('/prestamos/pendientes');

        $this->assertSame('rechazado', $prestamo->refresh()->estado);
        $this->assertStringContainsString('Motivo: Hay una actividad institucional en el salón.', $estudiante->notifications()->firstOrFail()->data['message']);
        $this->actingAs($estudiante)->get('/prestamos')->assertSee('Hay una actividad institucional en el salón.')->assertSee('Rechazada');
    }

    public function test_una_solicitud_atendida_no_se_atiende_otra_vez(): void
    {
        $this->actingAs($this->estudiante())->post('/prestamos', $this->datos($this->salon));
        $prestamo = Prestamo::firstOrFail();
        $this->actingAs(User::factory()->create(['rol' => 'funcionario']));

        $this->patch("/prestamos/{$prestamo->id}/aprobar");
        $this->from("/prestamos/{$prestamo->id}")->patch("/prestamos/{$prestamo->id}/rechazar", ['motivo' => 'Ya no se necesita.'])
            ->assertSessionHasErrors(['solicitud' => 'Esta solicitud ya fue atendida.']);
        $this->assertSame('aprobado', $prestamo->refresh()->estado);
    }

    public function test_el_administrador_atiende_sin_recibir_avisos(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $this->actingAs($this->estudiante())->post('/prestamos', $this->datos($this->salon));
        $prestamo = Prestamo::firstOrFail();

        $this->actingAs($admin)->get('/prestamos/pendientes')->assertOk()->assertSee('Salón 304B')->assertSee('Ingeniería de Sistemas');
        $this->patch("/prestamos/{$prestamo->id}/aprobar")->assertSessionHasNoErrors();
        $this->assertSame('aprobado', $prestamo->refresh()->estado);
        $this->assertSame(0, $admin->notifications()->count());
    }

    public function test_por_aprobar_lista_de_la_mas_antigua_a_la_mas_reciente_con_la_carrera(): void
    {
        $ana = $this->estudiante();
        $this->actingAs($ana)->post('/prestamos', $this->datos($this->salon, '13:30'));
        $this->travel(5)->minutes();
        $this->actingAs($this->estudiante())->post('/prestamos', $this->datos($this->salon, '15:00'));

        $this->actingAs(User::factory()->create(['rol' => 'funcionario']))->get('/prestamos/pendientes')->assertOk()
            ->assertSeeInOrder(['1:30 p. m.', '3:00 p. m.'])
            ->assertSee('Ingeniería de Sistemas · Bucaramanga')
            ->assertSee('2 solicitudes esperando respuesta');
    }

    /**
     * Cuatro pendientes: Ana y Beto (estudiantes de Sistemas) con el Salón 304B el martes 24 a la 1:30 p. m. (chocan),
     * un docente con el video beam verde el 25 y una estudiante de otra carrera con el video beam azul el 26.
     *
     * @return array{ana: User, docente: User, otra: Programa, deDocente: Prestamo}
     */
    private function pendientesVariadas(): array
    {
        $ana = User::factory()->create(['rol' => 'estudiante', 'programa_id' => $this->sistemas, 'name' => 'Ana Gómez', 'documento' => '555123']);
        $this->actingAs($ana)->post('/prestamos', $this->datos($this->salon, '13:30'));
        $this->travel(5)->minutes();
        $this->actingAs($this->estudiante())->post('/prestamos', $this->datos($this->salon, '13:30'));
        $this->travel(5)->minutes();

        $pendiente = fn (User $user, Equipo $equipo, string $fecha) => Prestamo::factory()->create([
            'equipo_id' => $equipo->id, 'solicitante_id' => $user->id, 'estado' => 'pendiente',
            'fecha' => $fecha, 'fecha_esperada_devolucion' => $fecha, 'hora_inicio' => '09:00:00', 'hora_fin' => '10:30:00',
        ]);
        $docente = User::factory()->create(['rol' => 'docente', 'programa_id' => $this->sistemas]);
        $deDocente = $pendiente($docente, $this->videoBeam, '2026-11-25');
        $this->travel(5)->minutes();
        $otra = Programa::whereKeyNot($this->sistemas)->orderBy('id')->firstOrFail();
        $pendiente($this->estudiante($otra->id), $this->videoBeam2, '2026-11-26');

        return ['ana' => $ana, 'docente' => $docente, 'otra' => $otra, 'deDocente' => $deDocente];
    }

    public function test_por_aprobar_se_filtra_por_perfil_categoria_carrera_busqueda_y_choques(): void
    {
        ['otra' => $otra] = $this->pendientesVariadas();
        $salones = $this->salon->categoria_id;
        $this->actingAs(User::factory()->create(['rol' => 'funcionario']));

        $this->get('/prestamos/pendientes')->assertOk()
            ->assertSee('4 solicitudes esperando respuesta')
            ->assertSee('Perfil')->assertSee('Categoría')->assertSee('Carrera del solicitante')->assertSee('Ordenar')
            ->assertDontSee('Limpiar todo');

        // Perfil
        $this->get('/prestamos/pendientes?perfil=docente')->assertOk()
            ->assertSee('1 solicitud esperando respuesta')->assertSee('Video beam verde')->assertDontSee('Salón 304B')
            ->assertSee('Quitar este filtro')->assertSee('Limpiar todo');
        // Categoría; si no es del tipo elegido, no se aplica
        $this->get('/prestamos/pendientes?categoria='.$salones)->assertOk()
            ->assertSee('2 solicitudes esperando respuesta')->assertDontSee('Video beam verde')->assertDontSee('Video beam azul');
        $this->get('/prestamos/pendientes?tipo=equipo&categoria='.$salones)->assertOk()
            ->assertSee('2 solicitudes esperando respuesta')->assertSee('Video beam azul')->assertDontSee('Salón 304B');
        // Carrera del solicitante
        $this->get('/prestamos/pendientes?programa='.$otra->id)->assertOk()
            ->assertSee('1 solicitud esperando respuesta')->assertSee('Video beam azul')->assertSee($otra->nombre.' · '.$otra->sede);
        // Búsqueda por documento del solicitante y por código del equipo
        $this->get('/prestamos/pendientes?buscar=555123')->assertOk()
            ->assertSee('1 solicitud esperando respuesta')->assertSee('Ana Gómez');
        $this->get('/prestamos/pendientes?buscar=VB-02')->assertOk()
            ->assertSee('1 solicitud esperando respuesta')->assertSee('Video beam azul');
        // Solo las que chocan con otra del mismo bloque
        $this->get('/prestamos/pendientes?choques=1')->assertOk()
            ->assertSee('2 solicitudes esperando respuesta')->assertSee('Salón 304B')->assertDontSee('Video beam verde')->assertDontSee('Video beam azul');
        // Sin resultados
        $this->get('/prestamos/pendientes?fecha=2026-12-01')->assertOk()
            ->assertSee('No hay solicitudes por aprobar con estos filtros.');
        // Valores que no existen se ignoran
        $this->get('/prestamos/pendientes?perfil=administrador&categoria=999&programa=abc&orden=otro')->assertOk()
            ->assertSee('4 solicitudes esperando respuesta');
    }

    public function test_por_aprobar_se_ordena_por_solicitud_o_por_dia_del_prestamo(): void
    {
        $this->pendientesVariadas();
        $this->actingAs(User::factory()->create(['rol' => 'funcionario']));

        $this->get('/prestamos/pendientes')->assertOk()->assertSee('más antigua primero')
            ->assertSeeInOrder(['Salón 304B', 'Video beam verde', 'Video beam azul']);
        $this->get('/prestamos/pendientes?orden=recientes')->assertOk()->assertSee('más reciente primero')
            ->assertSeeInOrder(['Video beam azul', 'Video beam verde', 'Salón 304B']);
        $this->get('/prestamos/pendientes?orden=proximas')->assertOk()
            ->assertSeeInOrder(['Martes 24/11/2026', 'Miércoles 25/11/2026', 'Jueves 26/11/2026']);
        $this->get('/prestamos/pendientes?orden=lejanas')->assertOk()
            ->assertSeeInOrder(['Jueves 26/11/2026', 'Miércoles 25/11/2026', 'Martes 24/11/2026']);
    }

    public function test_al_atender_se_vuelve_a_la_lista_con_los_mismos_filtros(): void
    {
        ['deDocente' => $deDocente] = $this->pendientesVariadas();
        $this->actingAs(User::factory()->create(['rol' => 'funcionario']));

        // La lista y el detalle llevan los filtros
        $this->get('/prestamos/pendientes?tipo=equipo&orden=recientes')->assertOk()
            ->assertSee('name="volver" value="'.e('tipo=equipo&orden=recientes').'"', false);
        $this->get(route('prestamos.show', [$deDocente, 'volver' => 'tipo=equipo&orden=recientes']))->assertOk()
            ->assertSee(e(route('prestamos.pendientes', ['tipo' => 'equipo', 'orden' => 'recientes'])), false);

        // Al aprobar vuelve con los filtros conocidos; lo desconocido se descarta
        $this->patch("/prestamos/{$deDocente->id}/aprobar", ['volver' => 'tipo=equipo&orden=recientes&malo[]=x&otro=1'])
            ->assertRedirect(route('prestamos.pendientes', ['tipo' => 'equipo', 'orden' => 'recientes']));
        $this->assertSame('aprobado', $deDocente->refresh()->estado);
    }

    // ---------- Docentes ----------

    private function docente(): User
    {
        return User::factory()->create(['rol' => 'docente', 'programa_id' => null, 'name' => 'Carlos Docente']);
    }

    private function otroSalon(string $codigo): Equipo
    {
        return $this->conHorario(Equipo::create(['categoria_id' => $this->salon->categoria_id, 'codigo_inventario' => $codigo, 'nombre' => "Salón {$codigo}", 'solicitable_por' => 'ambos']));
    }

    public function test_el_docente_pide_espacios_aprobados_solos_hasta_su_maximo(): void
    {
        $funcionario = User::factory()->create(['rol' => 'funcionario']);
        $docente = $this->docente();
        $sinCarreras = $this->otroSalon('401'); // Sin carreras habilitadas: solo docentes
        $tercero = $this->otroSalon('402');

        // Ve todos los espacios, también los que no son de su carrera o no tienen carreras
        $this->actingAs($docente)->get(route('prestamos.create', ['fecha' => self::MANANA, 'tipo' => 'espacio', 'categoria' => 'todas']))->assertOk()
            ->assertSee('Salón 304B')->assertSee('Salón 401')->assertSee('Salón 402');
        $this->get(route('prestamos.create', ['fecha' => self::MANANA]))->assertSee('Si el bloque está libre, queda aprobado de una vez.');

        // Dos espacios (su máximo por defecto): aprobados solos, sin avisar al funcionario
        $this->post('/prestamos', $this->datos($this->salon))->assertRedirect('/prestamos');
        $this->post('/prestamos', $this->datos($sinCarreras))->assertRedirect('/prestamos');
        $this->assertSame(['aprobado', 'aprobado'], Prestamo::orderBy('id')->pluck('estado')->all());
        $this->assertSame(['automatica', 'automatica'], Prestamo::orderBy('id')->pluck('aprobacion')->all());
        $this->assertSame(0, $funcionario->notifications()->count());

        // El tercero muestra el aviso y, si continúa, va a aprobación
        $this->post('/prestamos', $this->datos($tercero))->assertRedirect()->assertSessionHas('confirmar');
        $this->get(route('prestamos.create', ['equipo' => $tercero->id, 'fecha' => self::MANANA]))->assertSee('Enviar a aprobación');
        $this->post('/prestamos', $this->datos($tercero, extra: ['confirmado' => 1]))->assertRedirect('/prestamos');
        $this->assertSame('pendiente', Prestamo::where('equipo_id', $tercero->id)->value('estado'));
        $this->assertSame(1, $funcionario->notifications()->count());

        // Un estudiante no puede pedir el espacio sin carreras
        $this->actingAs($this->estudiante())->post('/prestamos', $this->datos($sinCarreras, '13:30'))->assertSessionHasErrors('solicitud');
    }

    public function test_la_pendiente_del_estudiante_sigue_y_el_funcionario_cancela_la_del_docente_para_aprobarla(): void
    {
        $estudiante = $this->estudiante();
        $this->actingAs($estudiante)->post('/prestamos', $this->datos($this->salon));
        $delEstudiante = Prestamo::firstOrFail();
        $this->travel(5)->minutes();

        // El docente pide el mismo salón y bloque: se le aprueba, y la del estudiante no se rechaza
        $docente = $this->docente();
        $this->actingAs($docente)->post('/prestamos', $this->datos($this->salon))->assertRedirect('/prestamos');
        $delDocente = Prestamo::where('solicitante_id', $docente->id)->firstOrFail();
        $this->assertSame('aprobado', $delDocente->estado);
        $this->assertSame('pendiente', $delEstudiante->refresh()->estado);

        // El funcionario ve que primero debe cancelar la del docente
        $funcionario = User::factory()->create(['rol' => 'funcionario']);
        $this->actingAs($funcionario)->get("/prestamos/{$delEstudiante->id}")->assertOk()
            ->assertSee('primero debes cancelar la solicitud aprobada de')->assertSee('Carlos Docente')
            ->assertSee(e(route('prestamos.show', [$delDocente->id, 'desde' => $delEstudiante->id])), false);
        $this->patch("/prestamos/{$delEstudiante->id}/aprobar")->assertSessionHasErrors('solicitud');

        // Abre la del docente y la cancela con su motivo (obligatorio)
        $this->get(route('prestamos.show', [$delDocente->id, 'desde' => $delEstudiante->id]))->assertOk()
            ->assertSee('Motivo de la cancelación')->assertSee('name="desde" value="'.$delEstudiante->id.'"', false);
        $this->patch("/prestamos/{$delDocente->id}/cancelar-aprobada", ['motivo_cancelacion' => ' '])->assertSessionHasErrors('motivo_cancelacion');
        $motivo = 'Este salón ya estaba asignado para una actividad; solicita otro espacio.';
        $this->patch("/prestamos/{$delDocente->id}/cancelar-aprobada", ['motivo_cancelacion' => $motivo, 'desde' => $delEstudiante->id])
            ->assertRedirect(route('prestamos.show', $delEstudiante->id))->assertSessionHas('status');

        $delDocente->refresh();
        $this->assertSame(['cancelado', 'personal', $funcionario->id, $motivo], [$delDocente->estado, $delDocente->cancelada_por, $delDocente->cancelado_por_id, $delDocente->motivo_cancelacion]);
        $aviso = $docente->notifications()->firstOrFail();
        $this->assertSame(SolicitudCancelada::class, $aviso->type);
        $this->assertStringContainsString($motivo, $aviso->data['message']);
        $this->actingAs($docente)->get("/prestamos/{$delDocente->id}")->assertSee('La canceló')->assertSee($motivo);

        // Ahora sí se aprueba la del estudiante
        $this->actingAs($funcionario)->patch("/prestamos/{$delEstudiante->id}/aprobar")->assertSessionHasNoErrors();
        $this->assertSame('aprobado', $delEstudiante->refresh()->estado);
    }

    public function test_cancelar_una_aprobada_solo_si_esta_aprobada_y_su_bloque_no_ha_terminado(): void
    {
        $funcionario = User::factory()->create(['rol' => 'funcionario']);
        $this->actingAs($this->estudiante())->post('/prestamos', $this->datos($this->salon));
        $pendiente = Prestamo::firstOrFail();
        $this->actingAs($funcionario)->patch("/prestamos/{$pendiente->id}/cancelar-aprobada", ['motivo_cancelacion' => 'No aplica para pendientes.'])
            ->assertSessionHasErrors(['solicitud' => 'Solo se puede cancelar una solicitud aprobada que todavía no se ha entregado.']);

        $aprobada = $this->prestamoAprobado($this->estudiante(), $this->videoBeam);
        $this->get("/prestamos/{$aprobada->id}")->assertSee('Cancelar esta solicitud');
        // Terminó el bloque sin entrega: pasa sola a «No recogido» y ya no se cancela.
        $this->travelTo(Carbon::parse(self::MANANA.' 11:00:00'));
        $this->get("/prestamos/{$aprobada->id}")->assertDontSee('Cancelar esta solicitud')->assertSee('No recogido');
        $this->assertSame('no_recogido', $aprobada->refresh()->estado);
        $this->patch("/prestamos/{$aprobada->id}/cancelar-aprobada", ['motivo_cancelacion' => 'Ya terminó el bloque.'])
            ->assertSessionHasErrors(['solicitud' => 'Solo se puede cancelar una solicitud aprobada que todavía no se ha entregado.']);
    }

    // ---------- Cancelar ----------

    public function test_cancelar_dentro_del_tiempo_y_no_despues(): void
    {
        $estudiante = $this->estudiante();
        $prestamo = $this->prestamoAprobado($estudiante, $this->videoBeam, '09:00');
        $this->actingAs($estudiante);

        // Mañana a las 9:00 se puede cancelar hasta las 7:30 (90 minutos antes).
        $this->travelTo(Carbon::parse(self::MANANA.' 07:45:00'));
        $this->from('/prestamos')->patch("/prestamos/{$prestamo->id}/cancelar")
            ->assertSessionHasErrors(['solicitud' => 'Ya no se puede cancelar: se podía hasta 1 h 30 min antes de la hora de inicio.']);
        $this->assertSame('aprobado', $prestamo->refresh()->estado);

        $this->travelTo(Carbon::parse(self::MANANA.' 07:30:00'));
        $this->patch("/prestamos/{$prestamo->id}/cancelar")->assertRedirect('/prestamos');
        $this->assertSame('cancelado', $prestamo->refresh()->estado);
        $this->assertNotNull($prestamo->cancelado_en);
    }

    public function test_cancelar_una_pendiente_quita_el_aviso_y_nadie_cancela_lo_ajeno(): void
    {
        $funcionario = User::factory()->create(['rol' => 'funcionario']);
        $estudiante = $this->estudiante();
        $this->actingAs($estudiante)->post('/prestamos', $this->datos($this->salon));
        $prestamo = Prestamo::firstOrFail();
        $this->assertSame(1, $funcionario->notifications()->count());

        $this->actingAs($this->estudiante())->patch("/prestamos/{$prestamo->id}/cancelar")->assertForbidden();
        $this->actingAs($estudiante)->patch("/prestamos/{$prestamo->id}/cancelar")->assertRedirect('/prestamos');

        $this->assertSame('cancelado', $prestamo->refresh()->estado);
        $this->assertSame(0, $funcionario->notifications()->count());
    }

    // ---------- Pantallas y permisos ----------

    public function test_mis_solicitudes_muestra_estado_y_hasta_cuando_cancelar(): void
    {
        $estudiante = $this->estudiante();
        $this->prestamoAprobado($estudiante, $this->videoBeam, '13:30');

        $this->actingAs($estudiante)->get('/prestamos')->assertOk()
            ->assertSee('Mis solicitudes')
            ->assertSee('Video beam verde')
            ->assertSee('Martes 24/11/2026')
            ->assertSee('1:30 p. m. – 3:00 p. m.')
            ->assertSee('Aprobada automáticamente')
            ->assertSee('Puedes cancelarla hasta el 24/11/2026 a las 12:00 pm');
    }

    // ---------- Formulario por pasos ----------

    public function test_el_formulario_de_solicitud_va_paso_a_paso(): void
    {
        $sinHorario = Equipo::create(['categoria_id' => $this->videoBeam->categoria_id, 'codigo_inventario' => 'VB-03', 'nombre' => 'Video beam rojo', 'solicitable_por' => 'ambos']);
        $this->actingAs($this->estudiante());

        // 1. El día
        $this->get('/prestamos/create')->assertOk()
            ->assertSee('Paso 1 de 5')->assertSee('¿Para qué día lo necesitas?')
            ->assertSee('un solo espacio o equipo')
            ->assertSee(e(route('prestamos.create', ['fecha' => self::MANANA])), false)
            ->assertDontSee('Video beam verde');

        // 2. Equipo o espacio
        $this->get(route('prestamos.create', ['fecha' => self::MANANA]))->assertOk()
            ->assertSee('Paso 2 de 5')->assertSee('¿Qué necesitas pedir?')
            ->assertSee('Un equipo')->assertSee('Un espacio')
            ->assertSee(e(route('prestamos.create', ['fecha' => self::MANANA, 'tipo' => 'equipo'])), false);

        // 3. La categoría (o buscar)
        $this->get(route('prestamos.create', ['fecha' => self::MANANA, 'tipo' => 'equipo']))->assertOk()
            ->assertSee('¿Qué tipo de equipo buscas?')->assertSee('Video beams')->assertSee('3 opciones')
            ->assertSee('Ej.: adaptador')->assertDontSee('Salones');

        // 4. Cuál: cada uno con sus bloques libres ese día
        $this->get(route('prestamos.create', ['fecha' => self::MANANA, 'tipo' => 'equipo', 'categoria' => $this->videoBeam->categoria_id]))->assertOk()
            ->assertSee('Elige el equipo')->assertSee('Video beam verde')->assertSee('9 bloques libres')
            ->assertSee($sinHorario->nombre)->assertSee('Sin horarios')
            ->assertSee(e(route('prestamos.create', ['fecha' => self::MANANA, 'tipo' => 'equipo', 'categoria' => $this->videoBeam->categoria_id, 'equipo' => $this->videoBeam->id])), false);

        // 5. El bloque y la confirmación
        $this->get(route('prestamos.create', ['equipo' => $this->salon->id, 'fecha' => self::MANANA]))->assertOk()
            ->assertSee('Paso 5 de 5')->assertSee('Elige el bloque')
            ->assertSee('aprobación del funcionario')
            ->assertSee('Vas a solicitar')->assertSee('Enviar a aprobación');
    }

    public function test_el_buscador_y_los_filtros_del_formulario(): void
    {
        // El video beam azul queda sin bloques libres mañana.
        $otro = $this->estudiante();
        foreach ($this->videoBeam2->horarios()->where('dia_semana', 2)->get() as $horario) {
            $this->prestamoAprobado($otro, $this->videoBeam2, substr($horario->hora_inicio, 0, 5));
        }
        $this->actingAs($this->estudiante());
        $lista = ['fecha' => self::MANANA, 'tipo' => 'equipo'];

        // Por nombre, sin importar mayúsculas
        $this->get(route('prestamos.create', $lista + ['q' => 'VERDE']))->assertOk()
            ->assertSee('Resultados para «VERDE»')->assertSee('Video beam verde')->assertDontSee('Video beam azul');
        // Por el nombre de la categoría
        $this->get(route('prestamos.create', $lista + ['q' => 'beams']))
            ->assertSee('Video beam verde')->assertSee('Video beam azul');
        // Sin resultados
        $this->get(route('prestamos.create', $lista + ['q' => 'adaptador']))
            ->assertSee('No encontramos equipos con esos filtros');
        // Los que tienen bloques libres van primero; el ocupado lo dice
        $this->get(route('prestamos.create', $lista + ['categoria' => 'todas']))
            ->assertSeeInOrder(['Video beam verde', 'Video beam azul'])
            ->assertSee('Sin bloques libres este día');
        // «Solo con horarios libres»
        $this->get(route('prestamos.create', $lista + ['categoria' => 'todas', 'libres' => 1]))
            ->assertSee('Video beam verde')->assertDontSee('Video beam azul');
        // Los espacios no se mezclan con los equipos
        $this->get(route('prestamos.create', $lista + ['categoria' => 'todas']))->assertDontSee('Salón 304B');
    }

    public function test_el_formulario_avisa_si_ese_dia_ya_no_alcanza_la_anticipacion(): void
    {
        // Hoy a las 8:00: los equipos (2 h) todavía alcanzan; los espacios (24 h) ya no.
        $this->actingAs($this->estudiante())->get(route('prestamos.create', ['fecha' => '2026-11-23']))->assertOk()
            ->assertSee('Para este día ya no alcanza: se pide con 24 h de anticipación')
            ->assertSee(e(route('prestamos.create', ['fecha' => '2026-11-23', 'tipo' => 'equipo'])), false)
            ->assertDontSee(e(route('prestamos.create', ['fecha' => '2026-11-23', 'tipo' => 'espacio'])), false);
    }

    public function test_desde_el_catalogo_el_formulario_empieza_por_el_dia_con_el_equipo_elegido(): void
    {
        $this->actingAs($this->estudiante())->get(route('prestamos.create', ['equipo' => $this->videoBeam->id]))->assertOk()
            ->assertSee('¿Para qué día lo necesitas?')
            ->assertSee('Video beam verde')
            ->assertSee(e(route('prestamos.create', ['fecha' => self::MANANA, 'tipo' => 'equipo', 'categoria' => $this->videoBeam->categoria_id, 'equipo' => $this->videoBeam->id])), false);
    }

    public function test_solicitudes_es_un_solo_modulo_con_el_boton_crear_solicitud(): void
    {
        $estudiante = $this->estudiante();

        // El menú tiene «Solicitudes» (antes eran «Solicitud» y «Mis solicitudes»)
        $this->actingAs($estudiante)->get('/dashboard')->assertOk()
            ->assertSee(route('prestamos.index'), false)
            ->assertDontSee('Mis solicitudes');
        $this->get('/prestamos')->assertOk()
            ->assertSee('Solicitudes')->assertSee('Mis solicitudes')
            ->assertSee('Crear solicitud')->assertSee(route('prestamos.create'), false);
        // El formulario tiene el enlace para volver a la lista
        $this->get('/prestamos/create')->assertSee('Nueva solicitud')->assertSee(route('prestamos.index'), false);

        // Los docentes también crean solicitudes
        $docente = User::factory()->create(['rol' => 'docente']);
        $this->actingAs($docente)->get('/prestamos')->assertOk()
            ->assertSee('Crear solicitud')->assertDontSee('siguiente etapa');
        $this->get('/prestamos/create')->assertOk()->assertSee('Nueva solicitud');
    }

    public function test_los_pasos_anteriores_se_abren_y_borran_lo_elegido_despues(): void
    {
        $this->actingAs($this->estudiante());
        $paso5 = ['fecha' => self::MANANA, 'tipo' => 'equipo', 'categoria' => $this->videoBeam->categoria_id, 'equipo' => $this->videoBeam->id];

        // En el paso 5 los cuatro anteriores son enlaces; cada uno conserva solo lo elegido antes de ese paso.
        $this->get(route('prestamos.create', $paso5))->assertOk()
            ->assertSee('Volver al paso 1: Día')
            ->assertSee('Volver al paso 3: Categoría')
            ->assertSee('Volver al paso 4: Equipo')
            ->assertSee('href="'.route('prestamos.create').'"', false)
            ->assertSee(e(route('prestamos.create', ['fecha' => self::MANANA, 'tipo' => 'equipo'])), false)
            ->assertSee(e(route('prestamos.create', ['fecha' => self::MANANA, 'tipo' => 'equipo', 'categoria' => $this->videoBeam->categoria_id])), false);

        // Al volver al paso 3 ya no se puede saltar al 4 ni al 5: hay que elegir de nuevo.
        $this->get(route('prestamos.create', ['fecha' => self::MANANA, 'tipo' => 'equipo']))->assertOk()
            ->assertSee('Paso 3 de 5')
            ->assertSee('Volver al paso 2: Equipo o espacio')
            ->assertDontSee('Volver al paso 4')
            ->assertDontSee('Video beam verde');
    }

    public function test_el_calendario_solo_deja_elegir_los_dias_permitidos(): void
    {
        // Martes 1 de diciembre: se puede pedir hasta el 15; el 8 es festivo y el 6 y el 13 son domingo.
        $this->travelTo(Carbon::parse('2026-12-01 08:00:00'));
        $pagina = $this->actingAs($this->estudiante())->get('/prestamos/create')->assertOk()
            ->assertSee('Diciembre de 2026')->assertDontSee('Diciembre De 2026')
            ->assertSeeInOrder(['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'])
            ->assertSee('Festivo: Día de la Inmaculada Concepción')
            ->assertSee('No hay préstamos en festivos')
            ->assertSee('Domingo: no hay préstamos')
            ->assertSee('Fuera del rango que se puede pedir');

        foreach (['2026-12-01', '2026-12-07', '2026-12-09', '2026-12-15'] as $habil) {
            $pagina->assertSee(route('prestamos.create', ['fecha' => $habil]).'"', false);
        }
        foreach (['2026-12-06', '2026-12-08', '2026-12-13', '2026-12-16', '2026-11-30'] as $bloqueado) {
            $pagina->assertDontSee(route('prestamos.create', ['fecha' => $bloqueado]).'"', false);
        }
    }

    public function test_no_se_puede_pedir_en_festivo(): void
    {
        $this->travelTo(Carbon::parse('2026-12-01 08:00:00'));
        $this->actingAs($this->estudiante())->post('/prestamos', $this->datos($this->videoBeam, '09:00', '2026-12-08'))
            ->assertSessionHasErrors(['solicitud' => 'El 08/12/2026 es festivo (Día de la Inmaculada Concepción): ese día no hay préstamos.']);
        $this->assertSame(0, Prestamo::count());
        $this->assertNotContains('2026-12-08', app(PrestamoService::class)->fechasSolicitables()->map->toDateString()->all());
    }

    public function test_permisos_de_cada_rol(): void
    {
        $prestamo = $this->prestamoAprobado($this->estudiante(), $this->videoBeam);
        $docente = User::factory()->create(['rol' => 'docente']);
        $otroEstudiante = $this->estudiante();

        // Los docentes solicitan; el personal no.
        $this->actingAs($docente)->get('/prestamos/create')->assertOk()->assertDontSee('siguiente etapa');
        $this->actingAs($docente)->post('/prestamos', $this->datos($this->videoBeam2))->assertRedirect('/prestamos');
        $this->actingAs(User::factory()->create(['rol' => 'administrador']))->get('/prestamos/create')->assertForbidden();
        // Solo el personal cancela una aprobada ajena.
        $this->actingAs($otroEstudiante)->patch("/prestamos/{$prestamo->id}/cancelar-aprobada", ['motivo_cancelacion' => 'Porque sí, sin razón.'])->assertForbidden();
        $this->assertSame('aprobado', $prestamo->refresh()->estado);

        $this->actingAs($otroEstudiante)->get("/prestamos/{$prestamo->id}")->assertForbidden();
        $this->actingAs($otroEstudiante)->get('/prestamos/pendientes')->assertForbidden();
        $this->actingAs($otroEstudiante)->patch("/prestamos/{$prestamo->id}/aprobar")->assertForbidden();

        $this->actingAs(User::factory()->create(['rol' => 'funcionario']))->post('/prestamos', $this->datos($this->videoBeam2))->assertForbidden();
    }

    public function test_los_avisos_de_respuesta_llevan_a_mis_solicitudes(): void
    {
        $estudiante = $this->estudiante();
        $this->actingAs($estudiante)->post('/prestamos', $this->datos($this->salon));
        $prestamo = Prestamo::firstOrFail();
        $this->actingAs(User::factory()->create(['rol' => 'funcionario']))->patch("/prestamos/{$prestamo->id}/aprobar");

        $aviso = $estudiante->notifications()->firstOrFail();
        $this->assertSame(SolicitudRespondida::class, $aviso->type);
        $this->assertSame(route('prestamos.index').'#solicitud-'.$prestamo->id, $aviso->data['link']);
    }
}
