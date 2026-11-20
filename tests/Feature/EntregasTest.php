<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Configuracion;
use App\Models\Equipo;
use App\Models\Prestamo;
use App\Models\PrestamoFoto;
use App\Models\User;
use App\Notifications\SolicitudNoEntregada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Entregas: solo de una aprobada, al solicitante en persona, desde la anticipación configurada;
 * no se entrega un equipo que no se ha devuelto; «No se pudo entregar» y «No recogido».
 */
class EntregasTest extends TestCase
{
    use RefreshDatabase;

    private const HOY = '2026-11-23';

    private Equipo $videoBeam;

    private User $funcionario;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        // Lunes 23 de noviembre de 2026, 8:00 a. m.
        $this->travelTo(Carbon::parse(self::HOY.' 08:00:00'));

        $categoria = Categoria::create(['nombre' => 'Video beams', 'tipo' => 'equipo']);
        $this->videoBeam = Equipo::create(['categoria_id' => $categoria->id, 'codigo_inventario' => 'VB-01', 'nombre' => 'Video beam verde', 'solicitable_por' => 'ambos']);
        $this->funcionario = User::factory()->create(['rol' => 'funcionario']);
    }

    private function prestamo(string $estado = 'aprobado', string $inicio = '09:00', string $fin = '10:30', ?User $user = null, string $fecha = self::HOY): Prestamo
    {
        return Prestamo::factory()->create([
            'equipo_id' => $this->videoBeam->id, 'solicitante_id' => ($user ?? User::factory()->create(['rol' => 'estudiante']))->id,
            'estado' => $estado, 'fecha' => $fecha, 'fecha_esperada_devolucion' => $fecha, 'hora_inicio' => "{$inicio}:00", 'hora_fin' => "{$fin}:00",
        ]);
    }

    /** @return array<string, mixed> */
    private function datos(array $cambios = []): array
    {
        return $cambios + ['verificado' => '1', 'estado' => 'bueno', 'complementos' => 'Tapa del lente, 2 patas, cable de poder'];
    }

    public function test_se_entrega_al_solicitante_desde_la_anticipacion_con_fotos(): void
    {
        $ana = User::factory()->create(['rol' => 'estudiante', 'name' => 'Ana Gómez']);
        $prestamo = $this->prestamo(user: $ana);
        $this->actingAs($this->funcionario);

        // A las 8:00 todavía no: se entrega desde las 8:30 (30 minutos antes)
        $this->get('/entregas')->assertOk()->assertSee('Video beam verde')->assertSee('Se entrega desde las 8:30 a. m.')
            ->assertDontSee(route('entregas.create', $prestamo), false);
        $this->post("/prestamos/{$prestamo->id}/entrega", $this->datos())
            ->assertSessionHasErrors(['solicitud' => 'Todavía no se puede entregar: se entrega desde las 8:30 a. m.']);

        $this->travelTo(Carbon::parse(self::HOY.' 08:40:00'));
        $this->get('/entregas')->assertSee(route('entregas.create', $prestamo), false);
        $this->get("/prestamos/{$prestamo->id}/entrega")->assertOk()
            ->assertSee('Verifiqué que quien recibe es Ana Gómez')->assertSee('Devolver hasta')->assertSee('11:30 a. m.');

        // Datos obligatorios
        $this->post("/prestamos/{$prestamo->id}/entrega", [])->assertSessionHasErrors(['verificado', 'estado', 'complementos']);
        $this->post("/prestamos/{$prestamo->id}/entrega", $this->datos(['estado' => 'con_observaciones']))
            ->assertSessionHasErrors(['observaciones' => 'Escribe las observaciones: elegiste «Con observaciones».']);

        // Entrega con dos fotos
        $this->post("/prestamos/{$prestamo->id}/entrega", $this->datos([
            'estado' => 'con_observaciones',
            'observaciones' => 'Tiene un pequeño rayón en la parte derecha.',
            'condiciones' => 'Devolverlo limpio y con la tapa puesta.',
            'fotos' => [UploadedFile::fake()->image('frente.jpg'), UploadedFile::fake()->image('lado.png')],
        ]))->assertRedirect('/entregas')
            ->assertSessionHas('status', 'Entregaste «Video beam verde» a Ana Gómez. Debe devolverlo hasta las 11:30 a. m.');

        $prestamo->refresh();
        $this->assertSame('entregado', $prestamo->estado);
        $this->assertSame([$this->funcionario->id, 'con_observaciones', 'Devolverlo limpio y con la tapa puesta.'], [$prestamo->entrega->funcionario_id, $prestamo->entrega->estado, $prestamo->entrega->condiciones]);
        $this->assertSame('prestado', $this->videoBeam->refresh()->estado);
        $this->assertSame(2, $prestamo->entrega->fotos()->count());
        $foto = PrestamoFoto::firstOrFail();
        Storage::disk('local')->assertExists($foto->ruta);

        // No se entrega dos veces
        $this->post("/prestamos/{$prestamo->id}/entrega", $this->datos())
            ->assertSessionHasErrors(['solicitud' => 'Solo se entrega una solicitud aprobada que todavía no se ha entregado.']);

        // La foto la ven el personal y el solicitante, nadie más
        $this->get("/prestamos/fotos/{$foto->id}")->assertOk();
        $this->actingAs($ana)->get("/prestamos/fotos/{$foto->id}")->assertOk();
        $this->actingAs(User::factory()->create(['rol' => 'estudiante']))->get("/prestamos/fotos/{$foto->id}")->assertForbidden();
    }

    public function test_las_fotos_tienen_maximo_configurable_y_formatos_de_imagen(): void
    {
        $prestamo = $this->prestamo();
        $this->travelTo(Carbon::parse(self::HOY.' 08:45:00'));
        $this->actingAs($this->funcionario);
        $foto = fn (string $nombre) => UploadedFile::fake()->image($nombre);

        $this->post("/prestamos/{$prestamo->id}/entrega", $this->datos(['fotos' => [$foto('1.jpg'), $foto('2.jpg'), $foto('3.jpg'), $foto('4.jpg')]]))
            ->assertSessionHasErrors(['fotos' => 'Puedes subir máximo 3 fotos.']);
        $this->post("/prestamos/{$prestamo->id}/entrega", $this->datos(['fotos' => [UploadedFile::fake()->create('acta.pdf', 100, 'application/pdf')]]))
            ->assertSessionHasErrors(['fotos.0' => 'La foto 1 debe ser JPG, JPEG, PNG o WebP.']);

        Configuracion::where('clave', 'max_fotos')->update(['valor' => 1]);
        $this->post("/prestamos/{$prestamo->id}/entrega", $this->datos(['fotos' => [$foto('1.jpg'), $foto('2.webp')]]))
            ->assertSessionHasErrors(['fotos' => 'Puedes subir máximo 1 foto.']);
        $this->assertSame('aprobado', $prestamo->refresh()->estado);
    }

    public function test_no_se_entrega_un_equipo_que_no_se_ha_devuelto_y_se_marca_no_entregado(): void
    {
        $anterior = $this->prestamo('entregado', '09:00', '10:30', User::factory()->create(['rol' => 'estudiante', 'name' => 'Beto Ruiz', 'email' => 'beto@prestamos.test']));
        $beatriz = User::factory()->create(['rol' => 'docente', 'name' => 'Beatriz Docente']);
        $siguiente = $this->prestamo('aprobado', '10:30', '12:00', $beatriz);
        $this->travelTo(Carbon::parse(self::HOY.' 10:35:00'));
        $this->actingAs($this->funcionario);

        $this->get('/entregas')->assertSee('Todavía no se ha devuelto: lo tiene')->assertSee('Beto Ruiz');
        $this->get("/prestamos/{$siguiente->id}/entrega")->assertOk()
            ->assertSee('Este equipo no se puede entregar')->assertSee('beto@prestamos.test')
            ->assertDontSee('Registrar entrega</button>', false);
        $this->post("/prestamos/{$siguiente->id}/entrega", $this->datos())
            ->assertSessionHasErrors(['solicitud' => 'Este equipo no se puede entregar porque no ha sido devuelto por Beto Ruiz (préstamo del lunes 23/11/2026, de 9:00 a. m. – 10:30 a. m.). Comunícate con esa persona para que lo devuelva lo más pronto posible: beto@prestamos.test.']);

        // No se pudo entregar, con motivo
        $this->patch("/prestamos/{$siguiente->id}/no-entregado", ['motivo_no_entrega' => ''])->assertSessionHasErrors('motivo_no_entrega');
        $this->patch("/prestamos/{$siguiente->id}/no-entregado", ['motivo_no_entrega' => 'El préstamo anterior no se devolvió a tiempo.'])
            ->assertRedirect('/entregas')->assertSessionHas('status');

        $siguiente->refresh();
        $this->assertSame(['no_entregado', 'El préstamo anterior no se devolvió a tiempo.', $this->funcionario->id], [$siguiente->estado, $siguiente->motivo_no_entrega, $siguiente->no_entregado_por_id]);
        $aviso = $beatriz->notifications()->firstOrFail();
        $this->assertSame(SolicitudNoEntregada::class, $aviso->type);
        $this->assertStringContainsString('no se pudo entregar. Motivo: El préstamo anterior no se devolvió a tiempo.', $aviso->data['message']);
        $this->get('/entregas?ver=no_entregados')->assertSee('Beatriz Docente')->assertSee('El préstamo anterior no se devolvió a tiempo.');
        $this->actingAs($beatriz)->get("/prestamos/{$siguiente->id}")->assertSee('No entregado')->assertSee('El préstamo anterior no se devolvió a tiempo.');
        $this->assertSame('entregado', $anterior->refresh()->estado);
    }

    public function test_si_el_bloque_termina_sin_entrega_queda_no_recogido(): void
    {
        $prestamo = $this->prestamo();
        $this->actingAs($this->funcionario)->get('/entregas')->assertSee('Video beam verde');

        $this->travelTo(Carbon::parse(self::HOY.' 10:31:00'));
        $this->get('/entregas')->assertDontSee('Video beam verde');
        $this->assertSame('no_recogido', $prestamo->refresh()->estado);
        $this->get('/entregas?ver=no_recogidos')->assertSee('Video beam verde')->assertSee('El bloque terminó y nadie lo recogió.');
        $this->post("/prestamos/{$prestamo->id}/entrega", $this->datos())->assertSessionHasErrors('solicitud');
    }

    public function test_solo_el_personal_registra_entregas(): void
    {
        $prestamo = $this->prestamo();
        $this->travelTo(Carbon::parse(self::HOY.' 08:45:00'));

        foreach (['estudiante', 'docente'] as $rol) {
            $this->actingAs(User::factory()->create(['rol' => $rol]));
            $this->get('/entregas')->assertForbidden();
            $this->post("/prestamos/{$prestamo->id}/entrega", $this->datos())->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['rol' => 'administrador']))->post("/prestamos/{$prestamo->id}/entrega", $this->datos())->assertRedirect('/entregas');
        $this->assertSame('entregado', $prestamo->refresh()->estado);
    }
}
