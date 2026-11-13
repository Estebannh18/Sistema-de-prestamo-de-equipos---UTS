<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Misma regla de contraseña en todo el sistema: crear usuario, cambiarla en el perfil
        // y restablecerla. Mínimo 8 caracteres, con letras y números; máximo 72 por el límite de bcrypt.
        Password::defaults(fn () => Password::min(8)->max(72)->letters()->numbers());
    }
}
