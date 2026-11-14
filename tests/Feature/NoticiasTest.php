<?php

namespace Tests\Feature;

use App\Models\Noticia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NoticiasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Los archivos de las pruebas van a un disco falso, no a storage/app/private.
        Storage::fake('local');
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create(['rol' => $rol]);
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(array $cambios = []): array
    {
        return $cambios + [
            'titulo' => 'Sala de informática 301C cerrada',
            'contenido' => "La sala estará cerrada del 16 al 20 de noviembre.\nUsen la sala 302A.",
        ];
    }

    private function imagen(string $nombre = 'sala.jpg', int $kb = 200): UploadedFile
    {
        return UploadedFile::fake()->image($nombre, 800, 600)->size($kb);
    }

    private function pdf(string $nombre = 'horario.pdf', int $kb = 300): UploadedFile
    {
        return UploadedFile::fake()->create($nombre, $kb, 'application/pdf');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function roles(): array
    {
        return ['administrador' => ['administrador'], 'funcionario' => ['funcionario'], 'docente' => ['docente'], 'estudiante' => ['estudiante']];
    }

    // ---------- Ver ----------

    #[DataProvider('roles')]
    public function test_todos_los_roles_ven_las_noticias_en_el_inicio(string $rol): void
    {
        Noticia::factory()->create(['titulo' => 'Equipos restaurados en el salón 304B']);

        $respuesta = $this->actingAs($this->usuario($rol))->get('/dashboard')->assertOk();

        $respuesta->assertSee('Noticias')->assertSee('Equipos restaurados en el salón 304B');
        in_array($rol, ['administrador', 'funcionario'], true)
            ? $respuesta->assertSee('Crear noticia')
            : $respuesta->assertDontSee('Crear noticia');
    }

    public function test_el_inicio_muestra_solo_las_tres_mas_recientes(): void
    {
        foreach (['Primera', 'Segunda', 'Tercera', 'Cuarta'] as $i => $titulo) {
            Noticia::factory()->create(['titulo' => "Noticia {$titulo}", 'created_at' => now()->subDays(10 - $i)]);
        }

        $this->actingAs($this->usuario('estudiante'))->get('/dashboard')
            ->assertSeeInOrder(['Noticia Cuarta', 'Noticia Tercera', 'Noticia Segunda'])
            ->assertDontSee('Noticia Primera')
            ->assertSee('Ver todas');
    }

    public function test_sin_noticias_se_muestra_un_aviso(): void
    {
        $this->actingAs($this->usuario('docente'))->get('/dashboard')->assertSee('Todavía no hay noticias publicadas.');
    }

    public function test_la_lista_completa_pagina_de_nueve_en_nueve(): void
    {
        Noticia::factory()->count(10)->create();

        $this->actingAs($this->usuario('estudiante'))->get('/noticias')->assertOk()->assertSee('10 noticias')->assertSee('Página 1 de 2');
        $this->get('/noticias?page=2')->assertOk()->assertSee('Página 2 de 2');
    }

    public function test_el_menu_tiene_noticias_para_los_cuatro_roles(): void
    {
        foreach (User::ROLES as $rol) {
            $this->assertArrayHasKey('Noticias', $this->usuario($rol)->modulos());
        }
    }

    public function test_el_texto_se_muestra_escapado_y_con_saltos_de_linea(): void
    {
        $noticia = Noticia::factory()->create(['contenido' => "Primera línea\nSegunda <script>alert(1)</script>"]);

        $this->actingAs($this->usuario('estudiante'))->get("/noticias/{$noticia->id}")
            ->assertOk()
            ->assertSee('whitespace-pre-line', false)
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_sin_sesion_no_se_ven_noticias_ni_archivos(): void
    {
        $noticia = Noticia::factory()->create();
        $adjunto = $noticia->adjuntos()->create(['tipo' => 'documento', 'ruta' => 'noticias/1/a.pdf', 'nombre_original' => 'a.pdf', 'mime' => 'application/pdf', 'tamano' => 10]);

        $this->get('/noticias')->assertRedirect('/login');
        $this->get("/noticias/{$noticia->id}")->assertRedirect('/login');
        $this->get("/noticias/adjuntos/{$adjunto->id}")->assertRedirect('/login');
    }

    // ---------- Crear ----------

    public function test_el_funcionario_publica_una_noticia_solo_con_texto(): void
    {
        $funcionario = $this->usuario('funcionario');

        $respuesta = $this->actingAs($funcionario)->post('/noticias', $this->datos([
            'titulo' => '  Sala   301C  cerrada ',
            'contenido' => "  Cerrada del 16 al 20.\r\nUsen la 302A.  ",
        ]));

        $noticia = Noticia::first();
        $respuesta->assertRedirect("/noticias/{$noticia->id}")->assertSessionHas('status');
        $this->assertSame('Sala 301C cerrada', $noticia->titulo);
        $this->assertSame("Cerrada del 16 al 20.\nUsen la 302A.", $noticia->contenido);
        $this->assertTrue($noticia->autor->is($funcionario));
        $this->assertSame(0, $noticia->adjuntos()->count());
    }

    public function test_el_administrador_publica_con_fotos_y_documentos(): void
    {
        $this->actingAs($this->usuario('administrador'))->post('/noticias', $this->datos([
            'adjuntos' => [$this->imagen('salon-304b.jpg'), $this->pdf('cronograma.pdf'), UploadedFile::fake()->create('lista.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')],
        ]))->assertSessionHasNoErrors();

        $noticia = Noticia::with('adjuntos')->first();
        $this->assertSame(1, $noticia->imagenes()->count());
        $this->assertSame(2, $noticia->documentos()->count());
        $foto = $noticia->imagenes()->first();
        $this->assertSame('salon-304b.jpg', $foto->nombre_original);
        $this->assertStringStartsWith("noticias/{$noticia->id}/", $foto->ruta);
        Storage::disk('local')->assertExists($noticia->adjuntos->pluck('ruta')->all());
    }

    #[DataProvider('rolesSinPermiso')]
    public function test_docentes_y_estudiantes_no_pueden_crear_noticias(string $rol): void
    {
        $this->actingAs($this->usuario($rol));

        $this->get('/noticias/create')->assertForbidden();
        $this->post('/noticias', $this->datos())->assertForbidden();
        $this->assertSame(0, Noticia::count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rolesSinPermiso(): array
    {
        return ['docente' => ['docente'], 'estudiante' => ['estudiante']];
    }

    /**
     * Cada caso: campo con error, datos que se envían.
     *
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function datosInvalidos(): array
    {
        return [
            'título vacío' => ['titulo', ['titulo' => '']],
            'título muy corto' => ['titulo', ['titulo' => 'Sala']],
            'título muy largo' => ['titulo', ['titulo' => str_repeat('a', 151)]],
            'texto vacío' => ['contenido', ['contenido' => '   ']],
            'texto muy corto' => ['contenido', ['contenido' => 'Cerrada']],
            'texto muy largo' => ['contenido', ['contenido' => str_repeat('a', 5001)]],
        ];
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    #[DataProvider('datosInvalidos')]
    public function test_un_dato_invalido_no_publica_la_noticia(string $campo, array $cambios): void
    {
        $this->actingAs($this->usuario('funcionario'))->post('/noticias', $this->datos($cambios))->assertSessionHasErrors($campo);
        $this->assertSame(0, Noticia::count());
    }

    public function test_no_se_aceptan_archivos_de_otro_tipo(): void
    {
        $this->actingAs($this->usuario('funcionario'));

        $this->post('/noticias', $this->datos(['adjuntos' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')]]))
            ->assertSessionHasErrors(['adjuntos.0' => 'El archivo 1 no es de un tipo permitido. Se aceptan fotos JPG, PNG o WEBP, o documentos PDF, Word, Excel o PowerPoint.']);
        // Un programa con el nombre cambiado a .pdf tampoco pasa: se revisa el contenido real.
        $this->post('/noticias', $this->datos(['adjuntos' => [UploadedFile::fake()->create('horario.pdf', 10, 'application/x-msdownload')]]))
            ->assertSessionHasErrors('adjuntos.0');
        $this->post('/noticias', $this->datos(['adjuntos' => [UploadedFile::fake()->create('foto.gif', 10, 'image/gif')]]))
            ->assertSessionHasErrors('adjuntos.0');

        $this->assertSame(0, Noticia::count());
    }

    public function test_cada_archivo_pesa_maximo_5_mb_y_son_maximo_5(): void
    {
        $this->actingAs($this->usuario('funcionario'));

        $this->post('/noticias', $this->datos(['adjuntos' => [$this->pdf('ok.pdf'), $this->pdf('grande.pdf', 5121)]]))
            ->assertSessionHasErrors(['adjuntos.1' => 'El archivo 2 pesa más de 5 MB.']);
        $this->post('/noticias', $this->datos(['adjuntos' => array_map(fn ($i) => $this->imagen("f{$i}.jpg"), range(1, 6))]))
            ->assertSessionHasErrors(['adjuntos' => 'Puedes adjuntar máximo 5 archivos.']);
        $this->assertSame(0, Noticia::count());

        $this->post('/noticias', $this->datos(['adjuntos' => [$this->pdf('justo.pdf', 5120), ...array_map(fn ($i) => $this->imagen("f{$i}.png"), range(1, 4))]]))
            ->assertSessionHasNoErrors();
        $this->assertSame(5, Noticia::first()->adjuntos()->count());
    }

    // ---------- Ver archivos ----------

    public function test_cualquier_usuario_ve_las_fotos_y_descarga_los_documentos(): void
    {
        $this->actingAs($this->usuario('funcionario'))->post('/noticias', $this->datos(['adjuntos' => [$this->imagen('sala.jpg'), $this->pdf('cronograma.pdf')]]));
        $noticia = Noticia::first();
        $foto = $noticia->imagenes()->first();
        $documento = $noticia->documentos()->first();

        $this->actingAs($this->usuario('estudiante'));
        $this->get("/noticias/{$noticia->id}")->assertOk()->assertSee('Fotos')->assertSee('cronograma.pdf')->assertSee("/noticias/adjuntos/{$foto->id}", false);
        $this->get("/noticias/adjuntos/{$foto->id}")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get("/noticias/adjuntos/{$documento->id}")->assertOk()->assertDownload('cronograma.pdf');
    }

    // ---------- Editar y borrar ----------

    public function test_el_funcionario_edita_y_borra_solo_sus_noticias(): void
    {
        $funcionario = $this->usuario('funcionario');
        $suya = Noticia::factory()->create(['user_id' => $funcionario->id]);
        $ajena = Noticia::factory()->create();
        $this->actingAs($funcionario);

        $this->get("/noticias/{$suya->id}")->assertSee('Editar');
        $this->get("/noticias/{$ajena->id}")->assertDontSee("/noticias/{$ajena->id}/edit", false);
        $this->get("/noticias/{$ajena->id}/edit")->assertForbidden();
        $this->put("/noticias/{$ajena->id}", $this->datos())->assertForbidden();
        $this->delete("/noticias/{$ajena->id}")->assertForbidden();

        $this->put("/noticias/{$suya->id}", $this->datos(['titulo' => 'Título corregido']))->assertRedirect("/noticias/{$suya->id}");
        $this->assertSame('Título corregido', $suya->refresh()->titulo);
        $this->assertNotSame('Título corregido', $ajena->refresh()->titulo);
    }

    public function test_el_administrador_edita_y_borra_cualquier_noticia(): void
    {
        $noticia = Noticia::factory()->create();
        $this->actingAs($this->usuario('administrador'));

        $this->put("/noticias/{$noticia->id}", $this->datos(['titulo' => 'Corregida por el administrador']))->assertSessionHasNoErrors();
        $this->assertSame('Corregida por el administrador', $noticia->refresh()->titulo);

        $this->delete("/noticias/{$noticia->id}")->assertRedirect('/noticias');
        $this->assertModelMissing($noticia);
    }

    public function test_docentes_y_estudiantes_no_editan_ni_borran(): void
    {
        $noticia = Noticia::factory()->create();

        foreach (['docente', 'estudiante'] as $rol) {
            $this->actingAs($this->usuario($rol));
            $this->get("/noticias/{$noticia->id}/edit")->assertForbidden();
            $this->put("/noticias/{$noticia->id}", $this->datos())->assertForbidden();
            $this->delete("/noticias/{$noticia->id}")->assertForbidden();
        }
        $this->assertModelExists($noticia);
    }

    public function test_al_editar_se_quitan_y_agregan_archivos(): void
    {
        $funcionario = $this->usuario('funcionario');
        $this->actingAs($funcionario)->post('/noticias', $this->datos(['adjuntos' => [$this->imagen('vieja.jpg'), $this->pdf('queda.pdf')]]));
        $noticia = Noticia::first();
        $vieja = $noticia->imagenes()->first();

        $this->get("/noticias/{$noticia->id}/edit")->assertOk()->assertSee('Archivos que ya tiene')->assertSee('vieja.jpg');

        $this->put("/noticias/{$noticia->id}", $this->datos(['quitar' => [$vieja->id], 'adjuntos' => [$this->imagen('nueva.png')]]))->assertSessionHasNoErrors();

        $this->assertModelMissing($vieja);
        Storage::disk('local')->assertMissing($vieja->ruta);
        $this->assertEqualsCanonicalizing(['queda.pdf', 'nueva.png'], $noticia->adjuntos()->pluck('nombre_original')->all());
    }

    public function test_al_editar_no_se_pasa_del_maximo_de_archivos(): void
    {
        $funcionario = $this->usuario('funcionario');
        $this->actingAs($funcionario)->post('/noticias', $this->datos(['adjuntos' => array_map(fn ($i) => $this->imagen("f{$i}.jpg"), range(1, 4))]));
        $noticia = Noticia::first();

        $this->put("/noticias/{$noticia->id}", $this->datos(['adjuntos' => [$this->pdf('a.pdf'), $this->pdf('b.pdf')]]))
            ->assertSessionHasErrors(['adjuntos' => 'La noticia puede tener máximo 5 archivos. Ya tiene 4: quita alguno o sube menos.']);
        $this->assertSame(4, $noticia->adjuntos()->count());

        // Quitando uno, sí caben los dos nuevos.
        $this->put("/noticias/{$noticia->id}", $this->datos(['quitar' => [$noticia->adjuntos()->first()->id], 'adjuntos' => [$this->pdf('a.pdf'), $this->pdf('b.pdf')]]))
            ->assertSessionHasNoErrors();
        $this->assertSame(5, $noticia->adjuntos()->count());
    }

    public function test_no_se_puede_quitar_un_archivo_de_otra_noticia(): void
    {
        $funcionario = $this->usuario('funcionario');
        $this->actingAs($funcionario)->post('/noticias', $this->datos(['adjuntos' => [$this->pdf('ajeno.pdf')]]));
        $otra = Noticia::first();
        $propia = Noticia::factory()->create(['user_id' => $funcionario->id]);

        $this->put("/noticias/{$propia->id}", $this->datos(['quitar' => [$otra->adjuntos()->first()->id]]))->assertSessionHasErrors('quitar.0');
        $this->assertSame(1, $otra->adjuntos()->count());
    }

    public function test_al_borrar_la_noticia_se_borran_sus_archivos(): void
    {
        $this->actingAs($this->usuario('administrador'))->post('/noticias', $this->datos(['adjuntos' => [$this->imagen(), $this->pdf()]]));
        $noticia = Noticia::first();
        $rutas = $noticia->adjuntos()->pluck('ruta')->all();

        $this->delete("/noticias/{$noticia->id}")->assertRedirect('/noticias')->assertSessionHas('status');

        $this->assertSame(0, Noticia::count());
        $this->assertDatabaseCount('noticia_adjuntos', 0);
        Storage::disk('local')->assertMissing($rutas);
    }

    public function test_si_se_borra_el_autor_la_noticia_se_conserva(): void
    {
        $noticia = Noticia::factory()->create();
        $noticia->autor->delete();

        $this->assertNull($noticia->refresh()->user_id);
        $this->actingAs($this->usuario('estudiante'))->get("/noticias/{$noticia->id}")->assertOk()->assertSee('Usuario eliminado');
    }
}
