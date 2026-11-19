<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class DosFactoresTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function roles(): array
    {
        return array_combine(User::ROLES, array_map(fn ($rol) => [$rol], User::ROLES));
    }

    #[DataProvider('roles')]
    public function test_sin_2fa_activo_entra_solo_con_la_contrasena(string $rol): void
    {
        $user = User::factory()->create(['rol' => $rol]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    #[DataProvider('roles')]
    public function test_cualquier_rol_activa_el_2fa_y_luego_se_le_pide_el_codigo(string $rol): void
    {
        $user = User::factory()->create(['rol' => $rol]);

        $this->actingAs($user)->post('/user/two-factor-authentication')->assertRedirect();
        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);

        $codigo = app(Google2FA::class)->getCurrentOtp(decrypt($user->two_factor_secret));
        $this->actingAs($user)->post('/user/confirmed-two-factor-authentication', ['code' => $codigo])->assertRedirect();
        $this->assertNotNull($user->refresh()->two_factor_confirmed_at);

        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
    }

    public function test_un_codigo_de_confirmacion_incorrecto_no_activa_el_2fa(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/user/two-factor-authentication');
        $this->actingAs($user)->post('/user/confirmed-two-factor-authentication', ['code' => '000000']);

        $this->assertNull($user->refresh()->two_factor_confirmed_at);
    }

    public function test_el_secreto_se_guarda_cifrado(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/user/two-factor-authentication');
        $user->refresh();

        $this->assertNotSame(decrypt($user->two_factor_secret), $user->two_factor_secret);
        $this->assertArrayNotHasKey('two_factor_secret', $user->toArray());
    }

    public function test_se_puede_desactivar(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/user/two-factor-authentication');
        $this->actingAs($user)->delete('/user/two-factor-authentication')->assertRedirect();

        $this->assertNull($user->refresh()->two_factor_secret);
    }

    // ---------- Pantalla del código al iniciar sesión ----------

    /** Crea un usuario con el 2FA activo y confirmado, y hace el primer paso del inicio de sesión. */
    private function usuarioEnElSegundoPaso(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/user/two-factor-authentication');
        $codigo = app(Google2FA::class)->getCurrentOtp(decrypt($user->refresh()->two_factor_secret));
        $this->actingAs($user)->post('/user/confirmed-two-factor-authentication', ['code' => $codigo]);
        $this->post('/logout');

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');

        return $user->refresh();
    }

    public function test_la_pantalla_pide_solo_el_codigo_de_la_app(): void
    {
        $this->usuarioEnElSegundoPaso();

        $html = $this->get('/two-factor-challenge')->assertOk()
            ->assertSee('Verificación en dos pasos')
            ->assertSee('¿Perdiste acceso a tu app autenticadora?')
            ->getContent();

        // El campo de la app está activo; el de recuperación, oculto y deshabilitado (no se envía).
        $this->assertMatchesRegularExpression('/<input id="code"(?:(?!>).)*autofocus/s', $html);
        $this->assertDoesNotMatchRegularExpression('/<input id="code"(?:(?!>).)*\sdisabled/s', $html);
        $this->assertMatchesRegularExpression('/<input id="recovery_code"(?:(?!>).)*\sdisabled/s', $html);
        $this->assertMatchesRegularExpression('/x-show="recuperacion" style="display: none;"/', $html);
    }

    public function test_con_el_codigo_de_la_app_entra(): void
    {
        $user = $this->usuarioEnElSegundoPaso();
        // El código actual ya se usó para activar el 2FA y Fortify no deja repetirlo (protección contra reutilizarlo).
        // Se usa el de los siguientes 30 segundos, que Fortify también acepta.
        $google2fa = app(Google2FA::class);
        $codigo = $google2fa->oathTotp(decrypt($user->two_factor_secret), $google2fa->getTimestamp() + 1);

        $this->post('/two-factor-challenge', ['code' => $codigo])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_un_codigo_incorrecto_muestra_el_mensaje_en_espanol(): void
    {
        $this->usuarioEnElSegundoPaso();

        $this->from('/two-factor-challenge')->post('/two-factor-challenge', ['code' => '000000'])
            ->assertSessionHasErrors(['code' => 'El código no es correcto o ya cambió. Escribe el que aparece ahora en tu app.']);
        $this->assertGuest();
    }

    public function test_un_codigo_de_recuperacion_incorrecto_vuelve_con_ese_modo_abierto(): void
    {
        $this->usuarioEnElSegundoPaso();

        $this->from('/two-factor-challenge')->post('/two-factor-challenge', ['recovery_code' => 'malo-malo'])
            ->assertSessionHasErrors(['recovery_code' => 'Ese código de recuperación no es válido o ya se usó.']);

        $html = $this->get('/two-factor-challenge')->assertSee('Ese código de recuperación no es válido o ya se usó.')->getContent();
        $this->assertMatchesRegularExpression('/<input id="recovery_code"(?:(?!>).)*autofocus/s', $html);
        $this->assertMatchesRegularExpression('/<input id="code"(?:(?!>).)*\sdisabled/s', $html);
        $this->assertSee('Pide al administrador que restablezca tu 2FA', $html);
        $this->assertGuest();
    }

    public function test_con_un_codigo_de_recuperacion_valido_entra_y_ese_codigo_se_gasta(): void
    {
        $user = $this->usuarioEnElSegundoPaso();
        $codigos = json_decode(decrypt($user->two_factor_recovery_codes), true);

        $this->post('/two-factor-challenge', ['recovery_code' => $codigos[0]])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNotContains($codigos[0], json_decode(decrypt($user->refresh()->two_factor_recovery_codes), true));
    }

    private function assertSee(string $texto, string $html): void
    {
        $this->assertStringContainsString($texto, $html);
    }
}
