<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\Horario;
use App\Models\User;
use Database\Seeders\HorarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HorariosTest extends TestCase
{
    use RefreshDatabase;

    private Equipo $salon;

    protected function setUp(): void
    {
        parent::setUp();
        $categoria = Categoria::create(['nombre' => 'Salones', 'tipo' => 'espacio']);
        $this->salon = Equipo::create(['categoria_id' => $categoria->id, 'codigo_inventario' => 'SAL-304B', 'nombre' => 'Salón 304B', 'solicitable_por' => 'docente']);
    }

    private function administrador(): User
    {
        return User::factory()->create(['rol' => 'administrador']);
    }

    public function test_el_administrador_configura_bloques_distintos_por_dia(): void
    {
        $this->actingAs($this->administrador())->get("/equipos/{$this->salon->id}/horarios")->assertOk()
            ->assertSee('Lunes')->assertSee('Sábado')->assertDontSee('Domingo')
            ->assertSee('Copiar a todos los días');

        $this->put("/equipos/{$this->salon->id}/horarios", ['bloques' => [
            1 => [['inicio' => '06:00', 'fin' => '07:30'], ['inicio' => '07:31', 'fin' => '09:00']],
            2 => [['inicio' => '09:30', 'fin' => '11:00']],
            6 => [['inicio' => '08:00', 'fin' => '09:00'], ['inicio' => '', 'fin' => '']], // la fila vacía se ignora
        ]])->assertRedirect("/equipos/{$this->salon->id}/horarios")->assertSessionHas('status');

        $this->assertSame(4, $this->salon->horarios()->count());
        $this->assertSame(['07:31', '09:00'], [$this->salon->horarios()->where('dia_semana', 1)->get()[1]->inicio(), $this->salon->horarios()->where('dia_semana', 1)->get()[1]->fin()]);
        $this->assertSame(0, $this->salon->horarios()->where('dia_semana', 3)->count());
    }

    public function test_guardar_reemplaza_los_bloques_anteriores_y_vacio_los_quita(): void
    {
        $this->salon->horarios()->create(['dia_semana' => 1, 'hora_inicio' => '06:00', 'hora_fin' => '07:30']);
        $this->actingAs($this->administrador());

        $this->put("/equipos/{$this->salon->id}/horarios", ['bloques' => [3 => [['inicio' => '10:00', 'fin' => '11:30']]]]);
        $this->assertSame([3], $this->salon->horarios()->pluck('dia_semana')->all());

        $this->put("/equipos/{$this->salon->id}/horarios", [])->assertSessionHas('status');
        $this->assertSame(0, $this->salon->horarios()->count());
    }

    public function test_no_se_aceptan_bloques_que_se_cruzan_ni_al_reves_ni_domingos(): void
    {
        $this->actingAs($this->administrador());
        $url = "/equipos/{$this->salon->id}/horarios";

        $this->from($url)->put($url, ['bloques' => [1 => [['inicio' => '07:00', 'fin' => '08:30'], ['inicio' => '08:00', 'fin' => '09:30']]]])
            ->assertSessionHasErrors(['bloques.1' => 'El lunes, el bloque 8:00 a. m. – 9:30 a. m. se cruza con 7:00 a. m. – 8:30 a. m.']);
        $this->from($url)->put($url, ['bloques' => [2 => [['inicio' => '10:00', 'fin' => '09:00']]]])
            ->assertSessionHasErrors(['bloques.2.0.fin' => 'En cada bloque, la hora de fin debe ser después de la hora de inicio.']);
        $this->from($url)->put($url, ['bloques' => [7 => [['inicio' => '10:00', 'fin' => '11:00']]]])
            ->assertSessionHasErrors(['bloques' => 'Los horarios solo se configuran de lunes a sábado.']);
        $this->from($url)->put($url, ['bloques' => [1 => [['inicio' => '25:00', 'fin' => '26:00']]]])
            ->assertSessionHasErrors('bloques.1.0.inicio');

        $this->assertSame(0, $this->salon->horarios()->count());
    }

    public function test_bloques_que_solo_se_tocan_si_se_permiten(): void
    {
        $this->actingAs($this->administrador())->put("/equipos/{$this->salon->id}/horarios", ['bloques' => [
            1 => [['inicio' => '06:00', 'fin' => '07:30'], ['inicio' => '07:30', 'fin' => '09:00']],
        ]])->assertSessionHasNoErrors();

        $this->assertSame(2, $this->salon->horarios()->count());
    }

    public function test_solo_el_administrador_configura_horarios(): void
    {
        foreach (['funcionario', 'docente', 'estudiante'] as $rol) {
            $user = User::factory()->create(['rol' => $rol]);
            $this->actingAs($user)->get("/equipos/{$this->salon->id}/horarios")->assertForbidden();
            $this->actingAs($user)->put("/equipos/{$this->salon->id}/horarios", ['bloques' => []])->assertForbidden();
        }
    }

    public function test_el_detalle_muestra_los_horarios_por_dia(): void
    {
        $this->salon->horarios()->create(['dia_semana' => 2, 'hora_inicio' => '13:30', 'hora_fin' => '15:00']);

        $this->actingAs($this->administrador())->get("/equipos/{$this->salon->id}")->assertOk()
            ->assertSee('Horarios en que se presta')
            ->assertSee('1:30 p. m. – 3:00 p. m.')
            ->assertSee('Configurar horarios');
    }

    public function test_el_seeder_pone_bloques_institucionales_sin_tocar_los_que_ya_tienen(): void
    {
        $this->salon->horarios()->create(['dia_semana' => 1, 'hora_inicio' => '06:00', 'hora_fin' => '07:00']);
        $otro = Equipo::create(['categoria_id' => $this->salon->categoria_id, 'codigo_inventario' => 'SAL-305', 'nombre' => 'Salón 305', 'solicitable_por' => 'docente']);

        $this->seed(HorarioSeeder::class);
        $this->seed(HorarioSeeder::class);

        $this->assertSame(1, $this->salon->horarios()->count());
        // 9 bloques de lunes a viernes y 4 de la mañana el sábado.
        $this->assertSame(9 * 5 + 4, $otro->horarios()->count());
        $this->assertSame(0, $otro->horarios()->where('dia_semana', 7)->count());
        $this->assertSame(count(Horario::BLOQUES_INSTITUCIONALES), $otro->horarios()->where('dia_semana', 3)->count());
    }
}
