<div>
    <x-input-label for="categoria_id" value="Categoría" />
    <select id="categoria_id" name="categoria_id" class="mt-1 block w-full border-marca-linea focus:border-marca-oliva focus:ring-marca-oliva rounded-md shadow-sm" required>
        <option value="">Elige una categoría</option>
        @foreach ($categorias as $opcion)
            <option value="{{ $opcion->id }}" @selected((string) old('categoria_id', $categoriaActual) === (string) $opcion->id)>{{ $opcion->nombre }}</option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('categoria_id')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="codigo_inventario" value="Código de inventario" />
    <x-text-input id="codigo_inventario" name="codigo_inventario" type="text" class="mt-1 block w-full" :value="old('codigo_inventario', $equipo?->codigo_inventario)" required />
    <x-input-error :messages="$errors->get('codigo_inventario')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="nombre" value="Nombre" />
    <x-text-input id="nombre" name="nombre" type="text" class="mt-1 block w-full" :value="old('nombre', $equipo?->nombre)" required />
    <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="solicitable_por" value="¿Quién lo puede pedir?" />
    <select id="solicitable_por" name="solicitable_por" class="mt-1 block w-full border-marca-linea focus:border-marca-oliva focus:ring-marca-oliva rounded-md shadow-sm" required>
        @foreach (['ambos' => 'Estudiantes y docentes', 'estudiante' => 'Solo estudiantes', 'docente' => 'Solo docentes'] as $valor => $texto)
            <option value="{{ $valor }}" @selected(old('solicitable_por', $equipo?->solicitable_por ?? 'ambos') === $valor)>{{ $texto }}</option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('solicitable_por')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="estado" value="Estado" />
    @if ($equipo?->estado === 'prestado')
        <p class="mt-1 text-sm text-marca-suave">Prestado: cambia solo al registrar la devolución.</p>
    @else
        <select id="estado" name="estado" class="mt-1 block w-full border-marca-linea focus:border-marca-oliva focus:ring-marca-oliva rounded-md shadow-sm" required>
            @foreach (['disponible', 'mantenimiento'] as $opcion)
                <option value="{{ $opcion }}" @selected(old('estado', $equipo?->estado ?? 'disponible') === $opcion)>{{ $opcion }}</option>
            @endforeach
        </select>
    @endif
    <x-input-error :messages="$errors->get('estado')" class="mt-2" />
</div>

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
    <x-input-label for="descripcion" value="Descripción (opcional)" />
    <textarea id="descripcion" name="descripcion" rows="3" class="mt-1 block w-full border-marca-linea focus:border-marca-oliva focus:ring-marca-oliva rounded-md shadow-sm">{{ old('descripcion', $equipo?->descripcion) }}</textarea>
    <x-input-error :messages="$errors->get('descripcion')" class="mt-2" />
</div>
