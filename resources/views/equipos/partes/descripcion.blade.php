{{-- Descripción opcional del equipo o espacio. --}}
<div class="{{ $clase ?? '' }}">
    <x-input-label for="descripcion" value="Descripción (opcional)" />
    <textarea id="descripcion" name="descripcion" rows="3" class="mt-1 block w-full campo-uts" placeholder="{{ $esEspacio ? 'Equipos instalados, recursos con los que cuenta…' : 'Accesorios incluidos, características…' }}">{{ old('descripcion', $equipo?->descripcion) }}</textarea>
    <x-input-error :messages="$errors->get('descripcion')" class="mt-2" />
</div>
