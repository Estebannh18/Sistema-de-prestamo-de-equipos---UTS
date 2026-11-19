<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConfiguracionTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, int> */
    private function valores(array $cambios = []): array
    {
        return $cambios + Configuracion::todos();
    }

    public function test_los_valores_por_defecto_se_cargan_al_migrar(): void
    {
        $this->assertSame(10, Configuracion::count());
        $estudiante = User::factory()->create(['rol' => 'estudiante']);
        $docente = User::factory()->create(['rol' => 'docente']);

        $this->assertSame(1, Configuracion::maximo($estudiante, espacio: false));
        $this->assertSame(1, Configuracion::maximo($estudiante, espacio: true));
        $this->assertSame(2, Configuracion::maximo($docente, espacio: false));
        $this->assertSame(90, Configuracion::minutosCancelacion($estudiante));
        $this->assertSame(120, Configuracion::minutosAnticipacion($estudiante, espacio: false));
        $this->assertSame(1440, Configuracion::minutosAnticipacion($estudiante, espacio: true));
        $this->assertSame('1 h 30 min', Configuracion::duracion(90));
        $this->assertSame('24 h', Configuracion::duracion(1440));
        $this->assertSame('45 min', Configuracion::duracion(45));
    }

    public function test_el_administrador_ve_y_cambia_la_configuracion(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);

        $this->actingAs($admin)->get('/configuracion')->assertOk()
            ->assertSee('Máximo de préstamos aprobados')
            ->assertSee('Tiempo mínimo para cancelar')
            ->assertSee('Anticipación mínima para solicitar')
            ->assertSee('Espacios, para estudiantes');

        $this->put('/configuracion', $this->valores(['max_equipos_estudiante' => 3, 'cancelacion_estudiante' => 60]))
            ->assertRedirect('/configuracion')->assertSessionHas('status');

        $this->assertSame(3, Configuracion::valor('max_equipos_estudiante'));
        $this->assertSame(60, Configuracion::valor('cancelacion_estudiante'));
        $this->assertSame(2, Configuracion::valor('max_equipos_docente'));
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function invalidos(): array
    {
        return [
            'máximo en cero' => ['max_equipos_estudiante', 0],
            'máximo con letras' => ['max_espacios_docente', 'dos'],
            'máximo muy alto' => ['max_espacios_estudiante', 21],
            'cancelación negativa' => ['cancelacion_docente', -5],
            'anticipación vacía' => ['anticipacion_equipos_estudiante', ''],
            'anticipación de más de dos semanas' => ['anticipacion_espacios_docente', 20161],
        ];
    }

    #[DataProvider('invalidos')]
    public function test_un_valor_invalido_no_se_guarda(string $clave, mixed $valor): void
    {
        $antes = Configuracion::valor($clave);

        $this->actingAs(User::factory()->create(['rol' => 'administrador']))
            ->from('/configuracion')->put('/configuracion', $this->valores([$clave => $valor]))
            ->assertSessionHasErrors($clave);

        $this->assertSame($antes, Configuracion::valor($clave));
    }

    public function test_los_mensajes_dicen_que_valor_esta_mal(): void
    {
        $this->actingAs(User::factory()->create(['rol' => 'administrador']))
            ->put('/configuracion', $this->valores(['max_equipos_estudiante' => 0]))
            ->assertSessionHasErrors(['max_equipos_estudiante' => '«Máximo de préstamos aprobados · Equipos por estudiante» debe estar entre 1 y 20.']);
    }

    public function test_solo_el_administrador_entra(): void
    {
        foreach (['funcionario', 'docente', 'estudiante'] as $rol) {
            $user = User::factory()->create(['rol' => $rol]);
            $this->actingAs($user)->get('/configuracion')->assertForbidden();
            $this->actingAs($user)->put('/configuracion', $this->valores())->assertForbidden();
        }
    }

    public function test_el_menu_del_administrador_tiene_configuracion_y_por_aprobar(): void
    {
        $this->assertArrayHasKey('Configuración', User::factory()->create(['rol' => 'administrador'])->modulos());
        $this->assertArrayHasKey('Por aprobar', User::factory()->create(['rol' => 'administrador'])->modulos());
        $this->assertArrayHasKey('Por aprobar', User::factory()->create(['rol' => 'funcionario'])->modulos());
        $this->assertArrayNotHasKey('Configuración', User::factory()->create(['rol' => 'funcionario'])->modulos());
    }
}
