{{--
    Segundo paso del inicio de sesión para quien activó el 2FA.
    Por defecto se pide solo el código de 6 dígitos de la app autenticadora.
    El código de recuperación aparece únicamente si el usuario dice que perdió el acceso a la app.
    Solo se envía el campo visible: el otro queda deshabilitado.
--}}
@php
    // Si el código de recuperación que escribió no sirvió, la página vuelve abierta en ese modo.
    $conRecuperacion = $errors->has('recovery_code');
@endphp

<x-guest-layout>
    <div x-data="{
            recuperacion: @js($conRecuperacion),
            cambiar() {
                this.recuperacion = ! this.recuperacion;
                this.$nextTick(() => (this.recuperacion ? this.$refs.recuperacion : this.$refs.codigo).focus());
            },
         }">
        <div class="flex items-start gap-4">
            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-marca-limaclaro text-marca-oliva ring-2 ring-marca-lima">
                <x-icono nombre="seguridad" class="h-7 w-7" />
            </span>
            <div>
                <h1 class="titulo-seccion">Verificación en dos pasos</h1>
            </div>
        </div>

        <form method="POST" action="{{ route('two-factor.login.store') }}" class="mt-6">
            @csrf

            {{-- Modo normal: código de la app autenticadora --}}
            <div x-show="! recuperacion" @style(['display: none' => $conRecuperacion])>
                <p class="text-sm text-marca-suave">
                    Abre tu app autenticadora (Google Authenticator, Microsoft Authenticator u otra) y escribe el código de 6 dígitos de <strong class="text-marca-noche">Préstamo de equipos</strong>.
                </p>

                <label for="code" class="mt-5 block font-display text-sm font-bold text-marca-noche">Código de la app</label>
                <input id="code" name="code" type="text" x-ref="codigo"
                       inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" placeholder="000000"
                       @disabled($conRecuperacion) x-bind:disabled="recuperacion" @if (! $conRecuperacion) autofocus @endif
                       x-on:input="$el.value = $el.value.replace(/\D/g, '')"
                       class="campo-uts mt-1 block w-full py-3 text-center font-display text-3xl font-extrabold tracking-[0.5em] text-marca-noche placeholder:text-marca-linea">
                <p class="mt-2 flex items-center gap-1.5 text-xs text-marca-suave">
                    <x-icono nombre="reloj" class="h-4 w-4 shrink-0" />
                    El código cambia cada 30 segundos. Escribe el que aparece en este momento.
                </p>
                <x-input-error :messages="$errors->get('code')" class="mt-2" />
            </div>

            {{-- Modo recuperación: solo si perdió el acceso a la app --}}
            <div x-show="recuperacion" @style(['display: none' => ! $conRecuperacion])>
                <div class="flex gap-3 rounded-2xl border-2 border-marca-lima bg-marca-limaclaro px-4 py-3 text-sm text-marca-noche">
                    <x-icono nombre="llave" class="mt-0.5 h-5 w-5 shrink-0 text-marca-oliva" />
                    <p>Usa uno de los <strong>códigos de recuperación</strong> que guardaste cuando activaste el 2FA. Cada código sirve <strong>una sola vez</strong>.</p>
                </div>

                <label for="recovery_code" class="mt-5 block font-display text-sm font-bold text-marca-noche">Código de recuperación</label>
                <input id="recovery_code" name="recovery_code" type="text" x-ref="recuperacion"
                       autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="21" placeholder="abcde12345-fghij67890"
                       @disabled(! $conRecuperacion) x-bind:disabled="! recuperacion" @if ($conRecuperacion) autofocus @endif
                       class="campo-uts mt-1 block w-full py-3 text-center font-mono text-lg tracking-wider text-marca-noche placeholder:text-marca-linea">
                <x-input-error :messages="$errors->get('recovery_code')" class="mt-2" />
                <p class="mt-3 text-xs text-marca-suave">
                    ¿Tampoco tienes tus códigos de recuperación? Pide al administrador que restablezca tu 2FA: después podrás entrar solo con tu contraseña y activarlo de nuevo.
                </p>
            </div>

            <x-primary-button class="mt-6 w-full py-3">Verificar</x-primary-button>
        </form>

        <div class="mt-5 border-t border-marca-linea pt-4 text-center">
            <button type="button" class="enlace text-sm" x-on:click="cambiar()" x-show="! recuperacion" @style(['display: none' => $conRecuperacion])>
                ¿Perdiste acceso a tu app autenticadora?
            </button>
            <button type="button" class="enlace inline-flex items-center gap-1 text-sm" x-on:click="cambiar()" x-show="recuperacion" @style(['display: none' => ! $conRecuperacion])>
                <x-icono nombre="izquierda" class="h-4 w-4" />Volver a usar el código de la app
            </button>
        </div>
    </div>
</x-guest-layout>
