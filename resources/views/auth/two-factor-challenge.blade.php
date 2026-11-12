<x-guest-layout>
    <div class="mb-4 text-sm text-marca-suave">
        Ingresa el código de tu aplicación autenticadora o uno de tus códigos de recuperación.
    </div>

    <form method="POST" action="{{ route('two-factor.login.store') }}">
        @csrf

        <div>
            <x-input-label for="code" value="Código de autenticación" />
            <x-text-input id="code" class="block mt-1 w-full" type="text" name="code" inputmode="numeric" autofocus autocomplete="one-time-code" />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="recovery_code" value="Código de recuperación (opcional)" />
            <x-text-input id="recovery_code" class="block mt-1 w-full" type="text" name="recovery_code" autocomplete="one-time-code" />
            <x-input-error :messages="$errors->get('recovery_code')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>Verificar</x-primary-button>
        </div>
    </form>
</x-guest-layout>
