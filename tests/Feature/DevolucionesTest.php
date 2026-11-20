<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\Horario;
use App\Models\Mantenimiento;
use App\Models\Prestamo;
use App\Models\User;
use App\Notifications\DevolucionRespondida;
use App\Notifications\EquipoFueraDeServicio;
use App\Notifications\SolicitudCancelada;
use App\Notifications\SolicitudPorAprobar;
use App\Services\EntregaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Devoluciones: cada revisión queda guardada; aceptada (con o sin pendiente), por corregir
 * o rechazada (el equipo queda fuera de servicio y se cancelan sus solicitudes posteriores).
 */
class DevolucionesTest extends TestCase
{
    use RefreshDatabase;

    private const HOY = '2026-11-23';

    private Equipo $videoBeam;

    private User $funcionario;

    private User $ana;

    private Prestamo $prestamo;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(Carbon::parse(self::HOY.' 09:30:00'));

        $categoria = Categoria::create(['nombre' => 'Video beams', 'tipo' => 'equipo']);
        $this->videoBeam = Equipo::create(['categoria_id' => $categoria->id, 'codigo_inventario' => 'VB-01', 'nombre' => 'Video beam verde', 'solicitable_por' => 'ambos', 'estado' => 'prestado']);
        foreach (Horario::DIAS as $dia => $nombre) {
            foreach (Horario::BLOQUES_INSTITUCIONALES as [$inicio, $fin]) {
                $this->videoBeam->horarios()->create(['dia_semana' => $dia, 'hora_inicio' => $inicio, 'hora_fin' => $fin]);
            }
        }
        $this->funcionario = User::factory()->create(['rol' => 'funcionario']);
        $this->ana = User::factory()->create(['rol' => 'estudiante', 'name' => 'Ana Gómez']);

        // Préstamo de 9:00 a 10:30, entregado a las 8:50 con condiciones
        $this->prestamo = $this->solicitud('entregado', '09:00', '10:30', $this->ana);
        $this->prestamo->entrega()->create([
            'funcionario_id' => $this->funcionario->id, 'entregado_en' => Carbon::parse(self::HOY.' 08:50:00'), 'estado' => 'bueno',
            'complementos' => 'Tapa del lente, 2 patas, cable de poder', 'condiciones' => 'Devolverlo limpio y con la tapa puesta.',
        ]);
    }

    private function solicitud(string $estado, string $inicio, string $fin, ?User $user = null, string $fecha = self::HOY): Prestamo
    {
        return Prestamo::factory()->create([
            'equipo_id' => $this->videoBeam->id, 'solicitante_id' => ($user ?? User::factory()->create(['rol' => 'estudiante']))->id,
            'estado' => $estado, 'fecha' => $fecha, 'fecha_esperada_devolucion' => $fecha, 'hora_inicio' => "{$inicio}:00", 'hora_fin' => "{$fin}:00",
        ]);
    }

    /** @return array<string, mixed> */
    private function datos(array $cambios = []): array
    {
        return $cambios + [
            'estado' => 'bueno', 'complementos' => 'Tapa del lente, 2 patas, cable de poder', 'complementos_completos' => '1',
            'condiciones_cumplidas' => '1', 'resultado' => 'aceptada',
        ];
    }

    private function recibir(array $cambios = [])
    {
        return $this->post("/prestamos/{$this->prestamo->id}/devolucion", $this->datos($cambios));
    }

    public function test_se_acepta_a_tiempo_comparando_con_la_entrega(): void
    {
        $this->travelTo(Carbon::parse(self::HOY.' 10:45:00'));
        $this->actingAs($this->funcionario);

        $this->get('/devoluciones')->assertOk()->assertSee('Video beam verde')->assertSee('devolver hasta las 11:30 a. m.');
        $this->get("/prestamos/{$this->prestamo->id}/devolucion")->assertOk()
            ->assertSee('Así se entregó')->assertSee('Tapa del lente, 2 patas, cable de poder')->assertSee('¿Cumplió las condiciones?');

        $this->post("/prestamos/{$this->prestamo->id}/devolucion", [])
            ->assertSessionHasErrors(['estado', 'complementos', 'complementos_completos', 'resultado', 'condiciones_cumplidas']);
        $this->recibir(['complementos_completos' => '0'])->assertSessionHasErrors('faltantes');

        $this->recibir(['fotos' => [UploadedFile::fake()->image('recibido.jpg')]])
            ->assertRedirect('/devoluciones')->assertSessionHas('status', 'Recibiste «Video beam verde» de Ana Gómez.');

        $this->prestamo->refresh();
        $devolucion = $this->prestamo->ultimaDevolucion;
        $this->assertSame(['devuelto', 0, 'aceptada', 1], [$this->prestamo->estado, $devolucion->minutos_retraso, $devolucion->resultado, $devolucion->fotos()->count()]);
        $this->assertSame('disponible', $this->videoBeam->refresh()->estado);
        $aviso = $this->ana->notifications()->firstOrFail();
        $this->assertSame([DevolucionRespondida::class, 'Recibimos «Video beam verde». Gracias.'], [$aviso->type, $aviso->data['message']]);

        // Ya no está pendiente de devolución
        $this->recibir()->assertSessionHasErrors(['solicitud' => 'Esta solicitud no está pendiente de devolución.']);
        $this->actingAs($this->ana)->get("/prestamos/{$this->prestamo->id}")->assertSee('Devolución: Aceptada')->assertSee('Tapa del lente, 2 patas, cable de poder');
    }

    public function test_con_retraso_y_con_pendiente_que_luego_se_resuelve(): void
    {
        // Límite: 10:30 + 60 min = 11:30. A las 11:50 va 20 minutos atrasado.
        $this->travelTo(Carbon::parse(self::HOY.' 11:50:00'));
        $this->actingAs($this->funcionario);
        $this->get('/devoluciones')->assertSee('Atrasado 20 min');

        $this->recibir(['complementos_completos' => '0', 'faltantes' => 'Falta una pata.', 'con_pendiente' => '1'])
            ->assertSessionHasErrors(['pendiente' => 'Describe qué quedó pendiente (por ejemplo: falta una pata del video beam).']);
        $this->recibir(['complementos' => 'Tapa del lente, 1 pata, cable de poder', 'complementos_completos' => '0', 'faltantes' => 'Falta una pata.', 'con_pendiente' => '1', 'pendiente' => 'Debe traer la pata que falta.'])
            ->assertSessionHas('status', 'Recibiste «Video beam verde» de Ana Gómez. Quedó un pendiente. Se devolvió con 20 min de retraso.');

        $devolucion = $this->prestamo->refresh()->ultimaDevolucion;
        $this->assertSame([20, 'Falta una pata.', 'Debe traer la pata que falta.'], [$devolucion->minutos_retraso, $devolucion->faltantes, $devolucion->pendiente]);
        $this->assertTrue($this->prestamo->tienePendiente());
        $this->assertStringContainsString('Queda un pendiente: Debe traer la pata que falta.', $this->ana->notifications()->firstOrFail()->data['message']);

        $this->get('/devoluciones?ver=con_pendiente')->assertSee('Debe traer la pata que falta.')->assertSee('Marcar como resuelto');
        $this->patch("/devoluciones/{$devolucion->id}/resolver")->assertSessionHas('status');
        $this->assertNotNull($devolucion->refresh()->pendiente_resuelto_en);
        $this->assertSame($this->funcionario->id, $devolucion->pendiente_resuelto_por_id);
        $this->get('/devoluciones?ver=con_pendiente')->assertSee('No hay devoluciones con pendientes.');
        $this->patch("/devoluciones/{$devolucion->id}/resolver")->assertSessionHasErrors('solicitud');
    }

    public function test_por_corregir_se_sigue_prestando_y_despues_se_acepta(): void
    {
        $this->travelTo(Carbon::parse(self::HOY.' 10:40:00'));
        $this->actingAs($this->funcionario);

        $this->recibir(['estado' => 'sucio', 'condiciones_cumplidas' => '0', 'resultado' => 'por_corregir'])->assertSessionHasErrors('motivo');
        $this->recibir(['estado' => 'sucio', 'condiciones_cumplidas' => '0', 'resultado' => 'por_corregir', 'motivo' => 'Está sucio y sin la tapa.', 'plazo' => '5 horas'])
            ->assertRedirect('/devoluciones?ver=por_corregir');

        $this->assertSame('por_corregir', $this->prestamo->refresh()->estado);
        $this->assertSame('prestado', $this->videoBeam->refresh()->estado);
        $this->assertSame('No se recibió «Video beam verde»: Está sucio y sin la tapa. Plazo: 5 horas.', $this->ana->notifications()->firstOrFail()->data['message']);
        $this->get('/devoluciones?ver=por_corregir')->assertSee('Está sucio y sin la tapa.')->assertSee('5 horas');

        // Se sigue pudiendo pedir para después, pero no se entrega hasta que se reciba
        $otro = User::factory()->create(['rol' => 'estudiante']);
        $horario = $this->videoBeam->horarios()->where('dia_semana', 2)->where('hora_inicio', '09:00:00')->value('id');
        $this->actingAs($otro)->post('/prestamos', ['equipo_id' => $this->videoBeam->id, 'fecha' => '2026-11-24', 'horario_id' => $horario])->assertSessionHasNoErrors();
        $siguiente = Prestamo::where('solicitante_id', $otro->id)->firstOrFail();
        $this->assertSame('aprobado', $siguiente->estado);
        $this->assertSame($this->prestamo->id, app(EntregaService::class)->sinDevolver($siguiente)?->id);

        // Vuelve y se acepta: queda el historial de las dos revisiones
        $this->travelTo(Carbon::parse(self::HOY.' 14:00:00'));
        $this->actingAs($this->funcionario)->get("/prestamos/{$this->prestamo->id}/devolucion")->assertSee('Revisión 1: Por corregir');
        $this->recibir()->assertRedirect('/devoluciones');
        $this->assertSame('devuelto', $this->prestamo->refresh()->estado);
        $this->assertSame(['por_corregir', 'aceptada'], $this->prestamo->devoluciones()->pluck('resultado')->all());
        $this->assertNull(app(EntregaService::class)->sinDevolver($siguiente));
    }

    public function test_el_rechazo_saca_de_servicio_y_cancela_las_solicitudes_posteriores(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $deLaTarde = $this->solicitud('aprobado', '13:30', '15:00');
        $deManana = $this->solicitud('pendiente', '09:00', '10:30', fecha: '2026-11-24');
        $this->funcionario->notify(new SolicitudPorAprobar($deManana));
        $anterior = $this->solicitud('devuelto', '07:30', '09:00');
        $this->travelTo(Carbon::parse(self::HOY.' 10:45:00'));
        $this->actingAs($this->funcionario);

        $this->get("/prestamos/{$this->prestamo->id}/devolucion")->assertSee('Se cancelarán')->assertSee('2 solicitudes posteriores');
        $this->recibir(['estado' => 'danado', 'resultado' => 'rechazada'])->assertSessionHasErrors('motivo');
        $this->recibir(['estado' => 'danado', 'resultado' => 'rechazada', 'motivo' => 'No enciende y tiene la carcasa rota.'])
            ->assertSessionHas('status', 'Rechazaste la devolución de «Video beam verde»: quedó fuera de servicio y se cancelaron sus solicitudes posteriores.');

        // La solicitud queda rechazada y el equipo fuera de servicio
        $this->assertSame('devolucion_rechazada', $this->prestamo->refresh()->estado);
        $mantenimiento = Mantenimiento::firstOrFail();
        $this->assertSame([null, $this->prestamo->id, 'Devolución rechazada: No enciende y tiene la carcasa rota.'], [$mantenimiento->hasta, $mantenimiento->prestamo_id, $mantenimiento->motivo]);
        $this->assertSame('mantenimiento', $this->videoBeam->refresh()->estado);

        // Las posteriores se cancelan con aviso; la anterior no se toca
        foreach ([$deLaTarde, $deManana] as $cancelada) {
            $cancelada->refresh();
            $this->assertSame(['cancelado', 'sistema', '«Video beam verde» quedó fuera de servicio.'], [$cancelada->estado, $cancelada->cancelada_por, $cancelada->motivo_cancelacion]);
            $aviso = $cancelada->solicitante->notifications()->firstOrFail();
            $this->assertSame(SolicitudCancelada::class, $aviso->type);
            $this->assertStringContainsString('Crea una nueva solicitud con los equipos o espacios disponibles.', $aviso->data['message']);
        }
        $this->assertSame('devuelto', $anterior->refresh()->estado);
        $this->assertSame(0, $this->funcionario->notifications()->where('type', SolicitudPorAprobar::class)->count());

        // Avisos al solicitante y a los administradores
        $this->assertSame('La devolución de «Video beam verde» fue rechazada. Motivo: No enciende y tiene la carcasa rota.', $this->ana->notifications()->firstOrFail()->data['message']);
        $this->assertSame(EquipoFueraDeServicio::class, $admin->notifications()->firstOrFail()->type);
        $this->actingAs($this->ana)->get("/prestamos/{$this->prestamo->id}")->assertSee('Devolución rechazada')->assertSee('No enciende y tiene la carcasa rota.');

        // Ya no se puede pedir; cuando se termina el mantenimiento, sí
        $this->get('/equipos')->assertDontSee('VB-01');
        $this->actingAs($admin)->patch("/mantenimientos/{$mantenimiento->id}/terminar")->assertRedirect();
        $this->assertSame('disponible', $this->videoBeam->refresh()->estado);
        $this->actingAs($this->ana)->get('/equipos')->assertSee('VB-01');
    }

    public function test_solo_el_personal_registra_devoluciones(): void
    {
        foreach (['estudiante', 'docente'] as $rol) {
            $this->actingAs(User::factory()->create(['rol' => $rol]));
            $this->get('/devoluciones')->assertForbidden();
            $this->recibir()->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['rol' => 'administrador']))->recibir()->assertRedirect('/devoluciones');
        $this->assertSame('devuelto', $this->prestamo->refresh()->estado);
    }
}
