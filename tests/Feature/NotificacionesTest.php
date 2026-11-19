<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\Horario;
use App\Models\Prestamo;
use App\Models\Programa;
use App\Models\User;
use App\Notifications\SolicitudPorAprobar;
use App\Services\PrestamoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Campana y bandeja de notificaciones (canal database), como en el taller 2FA.
 */
class NotificacionesTest extends TestCase
{
    use RefreshDatabase;

    private Equipo $salon;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-23 08:00:00'));
        $categoria = Categoria::create(['nombre' => 'Salones', 'tipo' => 'espacio']);
        $this->salon = Equipo::create(['categoria_id' => $categoria->id, 'codigo_inventario' => 'SAL-301', 'nombre' => 'Salón 301', 'solicitable_por' => 'ambos']);
        $this->salon->programas()->attach(Programa::where('snies', 101596)->value('id'));
        $this->salon->horarios()->create(['dia_semana' => 2, 'hora_inicio' => '09:00', 'hora_fin' => '10:30']);
    }

    /** Un estudiante pide el salón para el martes 24/11 a las 9:00: queda pendiente y avisa a los funcionarios. */
    private function solicitudDeSalon(?User $estudiante = null): Prestamo
    {
        $estudiante ??= User::factory()->create(['rol' => 'estudiante', 'programa_id' => Programa::where('snies', 101596)->value('id')]);

        return app(PrestamoService::class)->solicitar($estudiante, $this->salon, Carbon::parse('2026-11-24'), Horario::firstOrFail());
    }

    public function test_la_campana_muestra_las_notificaciones_sin_leer(): void
    {
        $funcionario = User::factory()->create(['rol' => 'funcionario']);
        $this->solicitudDeSalon();

        $this->actingAs($funcionario)->get('/dashboard')->assertOk()
            ->assertSee('total: 1,', false) // el número rojo de la campana (Alpine lo dibuja en el navegador)
            ->assertSee('Nueva solicitud de espacio')
            ->assertSee('Ir a la solicitud');

        $this->getJson('/notificaciones/resumen')->assertOk()
            ->assertJson(['total' => 1])
            ->assertJsonPath('items.0.titulo', 'Nueva solicitud de espacio')
            ->assertJsonPath('items.0.tipo', 'por_aprobar');
    }

    public function test_el_administrador_no_tiene_campana(): void
    {
        $this->actingAs(User::factory()->create(['rol' => 'administrador']))->get('/dashboard')
            ->assertOk()->assertDontSee('aria-label="Notificaciones', false);
    }

    public function test_abrir_marca_leida_y_lleva_a_la_solicitud(): void
    {
        $funcionario = User::factory()->create(['rol' => 'funcionario']);
        $prestamo = $this->solicitudDeSalon();
        $aviso = $funcionario->notifications()->firstOrFail();

        $this->actingAs($funcionario)->post("/notificaciones/{$aviso->id}/abrir")->assertRedirect(route('prestamos.show', $prestamo));

        $this->assertNotNull($aviso->refresh()->read_at);
        $this->getJson('/notificaciones/resumen')->assertJson(['total' => 0]);
    }

    public function test_nadie_abre_avisos_ajenos(): void
    {
        $funcionario = User::factory()->create(['rol' => 'funcionario']);
        $this->solicitudDeSalon();
        $aviso = $funcionario->notifications()->firstOrFail();

        $this->actingAs(User::factory()->create(['rol' => 'estudiante']))->post("/notificaciones/{$aviso->id}/abrir")
            ->assertRedirect('/notificaciones');
        $this->assertNull($aviso->refresh()->read_at);
    }

    public function test_cuando_un_funcionario_atiende_el_aviso_desaparece_para_los_demas(): void
    {
        [$uno, $dos] = User::factory()->count(2)->create(['rol' => 'funcionario'])->all();
        $prestamo = $this->solicitudDeSalon();
        $avisoDelDos = $dos->notifications()->firstOrFail();

        $this->actingAs($uno)->patch("/prestamos/{$prestamo->id}/aprobar");

        $this->assertSame(0, $dos->notifications()->where('type', SolicitudPorAprobar::class)->count());
        // Si el otro funcionario tenía el aviso abierto en la campana, al pulsarlo se le explica.
        $this->actingAs($dos)->post("/notificaciones/{$avisoDelDos->id}/abrir")
            ->assertRedirect('/notificaciones')->assertSessionHas('status', 'Ese aviso ya no está: la solicitud ya fue atendida.');
    }

    public function test_el_estudiante_recibe_la_respuesta_en_su_campana(): void
    {
        $estudiante = User::factory()->create(['rol' => 'estudiante', 'programa_id' => Programa::where('snies', 101596)->value('id')]);
        $prestamo = $this->solicitudDeSalon($estudiante);
        $this->actingAs(User::factory()->create(['rol' => 'funcionario']))->patch("/prestamos/{$prestamo->id}/rechazar", ['motivo' => 'Mantenimiento del video beam.']);

        $this->actingAs($estudiante)->get('/notificaciones')->assertOk()
            ->assertSee('Solicitud rechazada')
            ->assertSee('Motivo: Mantenimiento del video beam.')
            ->assertSee('Nueva')
            ->assertSee('Ver mi solicitud');
        $this->getJson('/notificaciones/resumen')->assertJson(['total' => 1])->assertJsonPath('items.0.tipo', 'rechazada');
    }

    public function test_marcar_todas_como_leidas(): void
    {
        $funcionario = User::factory()->create(['rol' => 'funcionario']);
        $this->solicitudDeSalon();
        $this->travel(1)->minutes();
        $this->salon->horarios()->create(['dia_semana' => 2, 'hora_inicio' => '13:30', 'hora_fin' => '15:00']);
        app(PrestamoService::class)->solicitar(
            User::factory()->create(['rol' => 'estudiante', 'programa_id' => Programa::where('snies', 101596)->value('id')]),
            $this->salon, Carbon::parse('2026-11-24'), Horario::where('hora_inicio', '13:30:00')->firstOrFail()
        );
        $this->assertSame(2, $funcionario->unreadNotifications()->count());

        $this->actingAs($funcionario)->from('/notificaciones')->post('/notificaciones/leer-todas')->assertRedirect('/notificaciones');

        $this->assertSame(0, $funcionario->unreadNotifications()->count());
        $this->assertSame(2, $funcionario->notifications()->count());
    }

    public function test_sin_sesion_no_hay_notificaciones(): void
    {
        $this->get('/notificaciones')->assertRedirect('/login');
        $this->getJson('/notificaciones/resumen')->assertUnauthorized();
    }
}
