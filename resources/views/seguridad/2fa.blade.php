<x-app-layout>
    <x-slot name="header">
        <h1 class="titulo-seccion">Autenticación de dos factores (2FA)</h1>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="tarjeta">
                <p class="text-marca-suave">
                    Es opcional. Si la activas, al iniciar sesión se te pedirá también un código de tu aplicación autenticadora.
                </p>

                @if (! $user->two_factor_secret)
                    {{-- Paso 1: activar --}}
                    <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-6">
                        @csrf
                        <x-primary-button>Activar 2FA</x-primary-button>
                    </form>
                @elseif (! $user->two_factor_confirmed_at)
                    {{-- Paso 2: escanear el código QR y confirmar --}}
                    <p class="mt-6 text-sm text-gray-600">
                        Escanea este código QR con tu aplicación autenticadora y escribe el código que te muestra.
                    </p>

                    <div class="mt-4 p-2 inline-block bg-white">
                        {!! $user->twoFactorQrCodeSvg() !!}
                    </div>

                    <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-4">
                        @csrf
                        <x-input-label for="code" value="Código de la aplicación" />
                        <x-text-input id="code" name="code" type="text" inputmode="numeric" class="mt-1 block w-full" autocomplete="one-time-code" required />
                        <x-input-error :messages="$errors->confirmTwoFactorAuthentication->get('code')" class="mt-2" />

                        <x-primary-button class="mt-4">Confirmar</x-primary-button>
                    </form>

                    <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-4">
                        @csrf
                        @method('DELETE')
                        <x-secondary-button type="submit">Cancelar</x-secondary-button>
                    </form>
                @else
                    {{-- Paso 3: activo --}}
                    <p class="aviso-ok mt-6">El 2FA está activo.</p>

                    <p class="mt-4 text-sm text-gray-600">Guarda estos códigos de recuperación en un lugar seguro:</p>

                    <div class="mt-2 p-4 bg-marca-crema border border-marca-linea rounded font-mono text-sm">
                        @foreach ($user->recoveryCodes() as $codigo)
                            <div>{{ $codigo }}</div>
                        @endforeach
                    </div>

                    <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-4">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>Desactivar 2FA</x-danger-button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
