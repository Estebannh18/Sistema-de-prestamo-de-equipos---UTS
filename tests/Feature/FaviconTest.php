<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El favicon de la UTS aparece en todas las páginas: portada, login (plantilla guest) y páginas internas (plantilla app).
 */
class FaviconTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_tres_plantillas_enlazan_el_favicon(): void
    {
        $etiqueta = '<link rel="icon" type="image/png" href="'.asset('images/favicon-uts.png').'">';

        $this->get('/')->assertOk()->assertSee($etiqueta, false);
        $this->get('/login')->assertOk()->assertSee($etiqueta, false);
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()->assertSee($etiqueta, false);
    }

    public function test_los_archivos_del_favicon_existen_y_son_imagenes(): void
    {
        $png = getimagesize(public_path('images/favicon-uts.png'));
        $this->assertSame('image/png', $png['mime']);
        $this->assertSame([512, 512], [$png[0], $png[1]]);

        // favicon.ico es el que piden los navegadores en páginas sin plantilla (errores, PDF).
        $this->assertGreaterThan(0, filesize(public_path('favicon.ico')));
        $this->assertSame('image/vnd.microsoft.icon', mime_content_type(public_path('favicon.ico')));
    }
}
