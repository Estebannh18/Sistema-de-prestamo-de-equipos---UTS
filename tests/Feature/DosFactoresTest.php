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
}
