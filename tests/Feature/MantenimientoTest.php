<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\Horario;
use App\Models\Mantenimiento;
use App\Models\Prestamo;
use App\Models\User;
use App\Notifications\SolicitudCancelada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Mantenimiento programado: el administrador o el funcionario lo programa por fechas;
 * si hay solicitudes en esas fechas, se avisa cuántas y se cancelan al confirmar.
 */
class MantenimientoTest extends TestCase
{
    use RefreshDatabase;

    private Equipo $videoBeam;

    protected function setUp(): void
    {
        parent::setUp();
        // Lunes 23 de noviembre de 2026, 8:00 a. m.
        $this->travelTo(Carbon::parse('2026-11-23 08:00:00'));

        $categoria = Categoria::create(['nombre' => 'Video beams', 'tipo' => 'equipo']);
        $this->videoBeam = Equipo::create(['categoria_id' => $categoria->id, 'codigo_inventario' => 'VB-02', 'nombre' => 'Video beam azul', 'solicitable_por' => 'ambos']);
        foreach (Horario::DIAS as $dia => $nombre) {
            foreach (Horario::BLOQUES_INSTITUCIONALES as [$inicio, $fin]) {
                $this->videoBeam->horarios()->create(['dia_semana' => $dia, 'hora_inicio' => $inicio, 'hora_fin' => $fin]);
            }
        }
    }

    private function funcionario(): User
    {
        return User::factory()->create(['rol' => 'funcionario']);
    }

    private function estudiante(): User
    {
        return User::factory()->create(['rol' => 'estudiante']);
    }

    private function solicitud(string $fecha, string $estado = 'aprobado', ?User $user = null): Prestamo
    {
        return Prestamo::factory()->create([
            'equipo_id' => $this->videoBeam->id, 'solicitante_id' => ($user ?? $this->estudiante())->id, 'estado' => $estado,
            'fecha' => $fecha, 'fecha_esperada_devolucion' => $fecha, 'hora_inicio' => '09:00:00', 'hora_fin' => '10:30:00',
        ]);
    }

    private function programar(array $datos)
    {
        return $this->post("/equipos/{$this->videoBeam->id}/mantenimientos", $datos + ['motivo' => 'Cambio de lámpara.']);
    }

    public function test_sin_solicitudes_en_esas_fechas_se_programa_sin_aviso(): void
    {
        $this->actingAs($this->funcionario());
        $this->get("/equipos/{$this->videoBeam->id}")->assertOk()->assertSee('Programar mantenimiento')->assertSee('No tiene mantenimientos programados.');
        $this->get("/equipos/{$this->videoBeam->id}/mantenimientos/create")->assertOk()->assertSee('Desde')->assertSee('Hasta (opcional)');

        $this->programar(['desde' => '2026-11-27', 'hasta' => '2026-11-28'])
            ->assertRedirect("/equipos/{$this->videoBeam->id}")
            ->assertSessionHas('status', 'Programaste el mantenimiento de «Video beam azul» del 27/11/2026 al 28/11/2026.');

        $this->assertSame(1, Mantenimiento::count());
        $this->assertSame('disponible', $this->videoBeam->refresh()->estado); // Todavía no empieza
        $this->get("/equipos/{$this->videoBeam->id}")->assertSee('Programado')->assertSee('Cambio de lámpara.')->assertSee('Quitar');
    }

    public function test_con_solicitudes_avisa_cuantas_son_y_las_cancela_al_confirmar(): void
    {
        $antes = $this->solicitud('2026-11-24');
        $aprobada = $this->solicitud('2026-11-25');
        $pendiente = $this->solicitud('2026-11-26', 'pendiente');
        $despues = $this->solicitud('2026-11-30');
        $this->actingAs($this->funcionario());

        // Primero el aviso: no se guarda nada
        $this->programar(['desde' => '2026-11-25', 'hasta' => '2026-11-28'])->assertOk()
            ->assertSee('Hay 2 solicitudes en esas fechas')->assertSee('¿Deseas continuar?')
            ->assertSee('Sí, programar y cancelar las 2 solicitudes')
            ->assertSee('name="confirmado" value="2026-11-25|2026-11-28"', false);
        $this->assertSame(0, Mantenimiento::count());
        $this->assertSame('aprobado', $aprobada->refresh()->estado);

        // Una confirmación de otras fechas no vale: vuelve a avisar
        $this->programar(['desde' => '2026-11-25', 'hasta' => '2026-11-30', 'confirmado' => '2026-11-25|2026-11-28'])->assertOk()
            ->assertSee('Hay 3 solicitudes en esas fechas');
        $this->assertSame(0, Mantenimiento::count());

        // Al confirmar: se programa y se cancelan con el motivo
        $this->programar(['desde' => '2026-11-25', 'hasta' => '2026-11-28', 'confirmado' => '2026-11-25|2026-11-28'])
            ->assertRedirect("/equipos/{$this->videoBeam->id}")
            ->assertSessionHas('status', 'Programaste el mantenimiento de «Video beam azul» del 25/11/2026 al 28/11/2026. Se cancelaron 2 solicitudes y se le avisó a cada solicitante.');

        foreach ([$aprobada, $pendiente] as $prestamo) {
            $prestamo->refresh();
            $this->assertSame(['cancelado', 'sistema'], [$prestamo->estado, $prestamo->cancelada_por]);
            $this->assertSame('«Video beam azul» estará en mantenimiento del 25/11/2026 al 28/11/2026. Motivo: Cambio de lámpara.', $prestamo->motivo_cancelacion);
            $aviso = $prestamo->solicitante->notifications()->firstOrFail();
            $this->assertSame(SolicitudCancelada::class, $aviso->type);
            $this->assertStringContainsString('Crea una nueva solicitud con los equipos o espacios disponibles.', $aviso->data['message']);
        }
        $this->assertSame('aprobado', $antes->refresh()->estado);
        $this->assertSame('aprobado', $despues->refresh()->estado);
    }

    public function test_en_esas_fechas_no_se_puede_pedir(): void
    {
        $this->videoBeam->mantenimientos()->create(['desde' => '2026-11-25', 'hasta' => '2026-11-26', 'motivo' => 'Cambio de lámpara.']);
        $this->actingAs($this->estudiante());

        // En el calendario esos días no se pueden elegir
        $calendario = $this->get(route('prestamos.create', ['equipo' => $this->videoBeam->id]))->assertOk()
            ->assertSee('En mantenimiento: Cambio de lámpara.')
            ->assertSee('Video beam azul estará en mantenimiento del 25/11/2026 al 26/11/2026: Cambio de lámpara.');
        $calendario->assertDontSee('fecha=2026-11-25')->assertDontSee('fecha=2026-11-26')
            ->assertSee('fecha=2026-11-24')->assertSee('fecha=2026-11-27');

        // En la lista de ese día aparece en mantenimiento
        $this->get(route('prestamos.create', ['fecha' => '2026-11-25', 'tipo' => 'equipo', 'categoria' => 'todas']))->assertSee('En mantenimiento este día');

        // Y el servidor no deja enviarla
        $horario = fn (string $fecha) => $this->videoBeam->horarios()->where('dia_semana', Carbon::parse($fecha)->dayOfWeekIso)->where('hora_inicio', '09:00:00')->value('id');
        $this->post('/prestamos', ['equipo_id' => $this->videoBeam->id, 'fecha' => '2026-11-25', 'horario_id' => $horario('2026-11-25')])
            ->assertSessionHasErrors(['solicitud' => 'El equipo «Video beam azul» está en mantenimiento ese día. Motivo: Cambio de lámpara.']);
        $this->post('/prestamos', ['equipo_id' => $this->videoBeam->id, 'fecha' => '2026-11-27', 'horario_id' => $horario('2026-11-27')])
            ->assertSessionHasNoErrors();
    }

    public function test_sin_fecha_de_fin_queda_fuera_de_servicio_hasta_que_se_termine(): void
    {
        $estudiante = $this->estudiante();
        $this->actingAs($this->funcionario());
        $this->programar(['desde' => '2026-11-23', 'motivo' => 'Se cayó y no enciende.'])->assertRedirect();
        $this->assertSame('mantenimiento', $this->videoBeam->refresh()->estado);
        $this->get("/equipos/{$this->videoBeam->id}")->assertSee('Fuera de servicio')->assertSee('desde el 23/11/2026, sin fecha de fin');

        // El estudiante ya no lo ve
        $this->actingAs($estudiante)->get('/equipos')->assertDontSee('VB-02');
        $this->get("/equipos/{$this->videoBeam->id}")->assertForbidden();

        // Al terminarlo vuelve a estar disponible desde hoy
        $mantenimiento = Mantenimiento::firstOrFail();
        $this->actingAs(User::factory()->create(['rol' => 'administrador']))->patch("/mantenimientos/{$mantenimiento->id}/terminar")
            ->assertRedirect("/equipos/{$this->videoBeam->id}");
        // Empezó hoy: se quita.
        $this->assertSame(0, Mantenimiento::count());
        $this->assertSame('disponible', $this->videoBeam->refresh()->estado);
        $this->actingAs($estudiante)->get('/equipos')->assertSee('VB-02');
    }

    public function test_terminar_uno_que_ya_empezo_deja_la_historia_hasta_ayer(): void
    {
        $mantenimiento = $this->videoBeam->mantenimientos()->create(['desde' => '2026-11-20', 'hasta' => null, 'motivo' => 'Revisión.']);
        Mantenimiento::sincronizarEstados();
        $this->assertSame('mantenimiento', $this->videoBeam->refresh()->estado);

        $this->actingAs($this->funcionario())->patch("/mantenimientos/{$mantenimiento->id}/terminar")->assertRedirect()
            ->assertSessionHas('status', 'Terminaste el mantenimiento de «Video beam azul»: se puede pedir desde hoy. Las solicitudes que se cancelaron no vuelven: deben pedirse de nuevo.');
        $this->assertSame('2026-11-22', $mantenimiento->refresh()->hasta->toDateString());
        $this->assertSame('disponible', $this->videoBeam->refresh()->estado);
        $this->patch("/mantenimientos/{$mantenimiento->id}/terminar")->assertSessionHasErrors('mantenimiento');
    }

    public function test_datos_invalidos_y_permisos(): void
    {
        $this->videoBeam->mantenimientos()->create(['desde' => '2026-11-27', 'hasta' => '2026-11-28', 'motivo' => 'Revisión.']);
        $this->actingAs($this->funcionario());

        $this->programar(['desde' => '2026-11-22'])->assertSessionHasErrors(['desde' => 'El mantenimiento no puede empezar antes de hoy.']);
        $this->programar(['desde' => '2026-11-25', 'hasta' => '2026-11-24'])->assertSessionHasErrors('hasta');
        $this->programar(['desde' => '2026-11-25', 'motivo' => ' '])->assertSessionHasErrors('motivo');
        // Se cruza con el que ya existe (uno sin fecha de fin desde el 25 lo cruza)
        $this->programar(['desde' => '2026-11-25'])->assertSessionHasErrors(['desde' => '«Video beam azul» ya tiene un mantenimiento que se cruza con esas fechas. Termínalo o elige otras fechas.']);
        $this->assertSame(1, Mantenimiento::count());

        foreach (['estudiante', 'docente'] as $rol) {
            $this->actingAs(User::factory()->create(['rol' => $rol]));
            $this->get("/equipos/{$this->videoBeam->id}/mantenimientos/create")->assertForbidden();
            $this->programar(['desde' => '2026-11-25'])->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['rol' => 'administrador']))
            ->programar(['desde' => '2026-11-30', 'hasta' => '2026-11-30'])->assertRedirect();
        $this->assertSame(2, Mantenimiento::count());
    }
}
