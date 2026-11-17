{{--
    Formulario de equipos y de espacios. Recibe $tipo ('equipo' o 'espacio'):
    - equipo: marca, modelo y quién lo puede pedir;
    - espacio: ubicación, capacidad y carreras recomendadas. Los espacios solo los piden docentes.
--}}
@php
    $esEspacio = $tipo === 'espacio';
    $elegidas = old('carreras', $equipo?->carrerasRecomendadas()->all() ?? []);
@endphp

@if ($esEspacio)
    <p class="mb-5 flex items-start gap-3 rounded-2xl bg-marca-limaclaro px-4 py-3 text-sm text-marca-bosque ring-1 ring-inset ring-[#dfe78f]">
        <x-icono nombre="info" class="mt-0.5 h-5 w-5 shrink-0" />
        Los espacios solo los pueden pedir docentes.
    </p>
@endif

<div>
    <x-input-label for="categoria_id" value="Categoría" />
    <select id="categoria_id" name="categoria_id" class="mt-1 block w-full campo-uts" required>
        <option value="">Elige una categoría</option>
        @foreach ($categorias as $opcion)
            <option value="{{ $opcion->id }}" @selected((string) old('categoria_id', $categoriaActual) === (string) $opcion->id)>{{ $opcion->nombre }}</option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('categoria_id')" class="mt-2" />
</div>

<div class="mt-4 grid gap-4 sm:grid-cols-[12rem_1fr]">
    <div>
        <x-input-label for="codigo_inventario" :value="$esEspacio ? 'Código' : 'Código de inventario'" />
        <x-text-input id="codigo_inventario" name="codigo_inventario" type="text" class="mt-1 block w-full" :value="old('codigo_inventario', $equipo?->codigo_inventario)" :placeholder="$esEspacio ? 'LAB-301' : 'POR-008'" required />
        <x-input-error :messages="$errors->get('codigo_inventario')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="nombre" value="Nombre" />
        <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full" :value="old('nombre', $equipo?->nombre)" :placeholder="$esEspacio ? 'Laboratorio de Inteligencia Artificial' : 'Portátil Lenovo ThinkPad'" required />
        <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
    </div>
</div>

@if ($esEspacio)
    <div class="mt-4 grid gap-4 sm:grid-cols-[1fr_12rem]">
        <div>
            <x-input-label for="ubicacion" value="Ubicación (opcional)" />
            <x-text-input id="ubicacion" name="ubicacion" type="text" class="mt-1 block w-full" :value="old('ubicacion', $equipo?->ubicacion)" placeholder="Edificio B, piso 3" />
            <x-input-error :messages="$errors->get('ubicacion')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="capacidad" value="Capacidad (personas)" />
            <x-text-input id="capacidad" name="capacidad" type="number" min="1" max="1000" class="mt-1 block w-full" :value="old('capacidad', $equipo?->capacidad)" />
            <x-input-error :messages="$errors->get('capacidad')" class="mt-2" />
        </div>
    </div>
@else
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="marca" value="Marca (opcional)" />
            <x-text-input id="marca" name="marca" type="text" class="mt-1 block w-full" :value="old('marca', $equipo?->marca)" />
            <x-input-error :messages="$errors->get('marca')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="modelo" value="Modelo (opcional)" />
            <x-text-input id="modelo" name="modelo" type="text" class="mt-1 block w-full" :value="old('modelo', $equipo?->modelo)" />
            <x-input-error :messages="$errors->get('modelo')" class="mt-2" />
        </div>
    </div>

    <div class="mt-4">
        <x-input-label for="solicitable_por" value="¿Quién lo puede pedir?" />
        <select id="solicitable_por" name="solicitable_por" class="mt-1 block w-full campo-uts" required>
            @foreach (['ambos' => 'Estudiantes y docentes', 'estudiante' => 'Solo estudiantes', 'docente' => 'Solo docentes'] as $valor => $texto)
                <option value="{{ $valor }}" @selected(old('solicitable_por', $equipo?->solicitable_por ?? 'ambos') === $valor)>{{ $texto }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('solicitable_por')" class="mt-2" />
    </div>
@endif

<div class="mt-4">
    <x-input-label for="estado" value="Estado" />
    @if ($equipo?->estado === 'prestado')
        <p class="mt-1 text-sm text-marca-suave">Prestado: cambia solo al registrar la devolución.</p>
    @else
        <select id="estado" name="estado" class="mt-1 block w-full campo-uts" required>
            @foreach (['disponible', 'mantenimiento'] as $opcion)
                <option value="{{ $opcion }}" @selected(old('estado', $equipo?->estado ?? 'disponible') === $opcion)>{{ $opcion }}</option>
            @endforeach
        </select>
    @endif
    <x-input-error :messages="$errors->get('estado')" class="mt-2" />
</div>

@if ($esEspacio)
    {{-- Carreras recomendadas: orientan al docente, no limitan quién lo pide. El buscador filtra la lista. --}}
    <fieldset class="mt-5" x-data="{ filtro: '' }">
        <legend class="block font-medium text-sm text-gray-700">Carreras recomendadas (opcional)</legend>
        <p class="mt-1 text-xs text-marca-suave">Sirven de orientación: cualquier docente puede pedir el espacio.</p>
        <div class="relative mt-2">
            <x-icono nombre="buscar" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-marca-gris" />
            <input type="search" x-model="filtro" placeholder="Buscar carrera" aria-label="Buscar carrera" class="block w-full pl-10 text-sm campo-uts">
        </div>
        <div class="mt-2 grid max-h-64 gap-1 overflow-y-auto rounded-2xl border-2 border-marca-linea p-2 sm:grid-cols-2">
            @foreach ($carreras as $carrera)
                <label x-show="@js(Str::lower(Str::ascii($carrera))).includes(filtro.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''))"
                       class="flex cursor-pointer items-start gap-2.5 rounded-xl px-3 py-2 text-sm text-marca-noche hover:bg-marca-crema has-[:checked]:bg-marca-limaclaro has-[:checked]:font-semibold">
                    <input type="checkbox" name="carreras[]" value="{{ $carrera }}" class="mt-0.5 rounded border-marca-linea text-marca-oliva focus:ring-marca-lima" @checked(in_array($carrera, $elegidas, true))>
                    {{ $carrera }}
                </label>
            @endforeach
        </div>
        <x-input-error :messages="$errors->get('carreras')" class="mt-2" />
        <x-input-error :messages="$errors->get('carreras.*')" class="mt-2" />
    </fieldset>
@endif

<div class="mt-4">
    <x-input-label for="descripcion" value="Descripción (opcional)" />
    <textarea id="descripcion" name="descripcion" rows="3" class="mt-1 block w-full campo-uts" placeholder="{{ $esEspacio ? 'Equipos instalados, recursos con los que cuenta…' : 'Accesorios incluidos, características…' }}">{{ old('descripcion', $equipo?->descripcion) }}</textarea>
    <x-input-error :messages="$errors->get('descripcion')" class="mt-2" />
</div>
