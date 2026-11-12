<div class="mt-4">
    <x-input-label for="name" value="Nombre" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $usuario?->name)" required />
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="email" value="Correo" />
    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $usuario?->email)" required />
    <x-input-error :messages="$errors->get('email')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="documento" value="Documento" />
    <x-text-input id="documento" name="documento" type="text" class="mt-1 block w-full" :value="old('documento', $usuario?->documento)" required />
    <x-input-error :messages="$errors->get('documento')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="telefono" value="Teléfono (opcional)" />
    <x-text-input id="telefono" name="telefono" type="text" class="mt-1 block w-full" :value="old('telefono', $usuario?->telefono)" />
    <x-input-error :messages="$errors->get('telefono')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="programa_dependencia" value="Programa o dependencia (opcional)" />
    <x-text-input id="programa_dependencia" name="programa_dependencia" type="text" class="mt-1 block w-full" :value="old('programa_dependencia', $usuario?->programa_dependencia)" />
    <x-input-error :messages="$errors->get('programa_dependencia')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="rol" value="Rol" />
    <select id="rol" name="rol" class="mt-1 block w-full campo-uts" required>
        @foreach ($roles as $rol)
            <option value="{{ $rol }}" @selected(old('rol', $usuario?->rol) === $rol)>{{ $rol }}</option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('rol')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="password" :value="$usuario ? 'Nueva contraseña (opcional)' : 'Contraseña'" />
    <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" :required="! $usuario" />
    <x-input-error :messages="$errors->get('password')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="password_confirmation" value="Confirmar contraseña" />
    <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" :required="! $usuario" />
</div>
