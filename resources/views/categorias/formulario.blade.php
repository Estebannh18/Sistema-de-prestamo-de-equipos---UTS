@php
    $tipoActual = old('tipo', $categoria?->tipo ?? 'equipo');
    $iconoActual = old('icono', $categoria?->icono);
    $opcion = 'flex cursor-pointer items-center gap-3 rounded-2xl border-2 border-marca-linea bg-white px-4 py-3 transition hover:border-marca-oliva has-[:checked]:border-marca-oliva has-[:checked]:bg-marca-limaclaro has-[:focus-visible]:ring-4 has-[:focus-visible]:ring-marca-lima/40';
@endphp

<div>
    <x-input-label for="nombre" value="Nombre" />
    <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full" :value="old('nombre', $categoria?->nombre)" required autofocus />
    <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="descripcion" value="Descripción (opcional)" />
    <textarea id="descripcion" name="descripcion" rows="3" class="mt-1 block w-full campo-uts">{{ old('descripcion', $categoria?->descripcion) }}</textarea>
    <x-input-error :messages="$errors->get('descripcion')" class="mt-2" />
</div>

<fieldset class="mt-5">
    <legend class="block font-medium text-sm text-gray-700">Tipo</legend>
    <div class="mt-2 grid gap-3 sm:grid-cols-2">
        <label class="{{ $opcion }}">
            <input type="radio" name="tipo" value="equipo" class="sr-only" @checked($tipoActual === 'equipo')>
            <x-icono nombre="equipos" class="h-6 w-6 shrink-0 text-marca-oliva" />
            <span>
                <span class="block font-display font-bold text-marca-noche">Equipos</span>
                <span class="block text-xs text-marca-suave">Portátiles, proyectores, cables…</span>
            </span>
        </label>
        <label class="{{ $opcion }}">
            <input type="radio" name="tipo" value="espacio" class="sr-only" @checked($tipoActual === 'espacio')>
            <x-icono nombre="salon" class="h-6 w-6 shrink-0 text-marca-oliva" />
            <span>
                <span class="block font-display font-bold text-marca-noche">Espacios</span>
                <span class="block text-xs text-marca-suave">Salones, laboratorios…</span>
            </span>
        </label>
    </div>
    <x-input-error :messages="$errors->get('tipo')" class="mt-2" />
</fieldset>

<fieldset class="mt-5">
    <legend class="block font-medium text-sm text-gray-700">Ícono (opcional)</legend>
    <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-5">
        @foreach (\App\Models\Categoria::ICONOS as $clave => $etiqueta)
            <label class="{{ $opcion }} flex-col justify-center !gap-1.5 !px-2 text-center">
                <input type="radio" name="icono" value="{{ $clave }}" class="sr-only" @checked($iconoActual === $clave)>
                <x-icono :nombre="$clave" class="h-6 w-6 text-marca-oliva" />
                <span class="text-xs font-semibold text-marca-noche">{{ $etiqueta }}</span>
            </label>
        @endforeach
    </div>
    <p class="mt-2 text-xs text-marca-suave">Si no eliges uno, se usa el ícono general del tipo.</p>
    <x-input-error :messages="$errors->get('icono')" class="mt-2" />
</fieldset>
