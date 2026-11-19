{{--
    Formulario de equipos y de espacios. Recibe $tipo ('equipo' o 'espacio'):
    - equipo: marca, modelo y quién lo puede pedir;
    - espacio: ubicación, capacidad y carreras habilitadas. Los docentes piden cualquier espacio;
      los estudiantes, solo los habilitados para su carrera.
    En pantallas grandes va en dos columnas para aprovechar el ancho de la tarjeta:
    a la izquierda los datos generales y a la derecha las carreras (espacio) o quién lo pide,
    el estado y la descripción (equipo). En celular queda en una sola columna.
--}}
@php
    $esEspacio = $tipo === 'espacio';
    $elegidas = old('carreras', $equipo?->carrerasHabilitadas()->all() ?? []);
@endphp

@if ($esEspacio)
    <p class="mb-5 flex items-start gap-3 rounded-2xl bg-marca-limaclaro px-4 py-3 text-sm text-marca-bosque ring-1 ring-inset ring-[#dfe78f]">
        <x-icono nombre="info" class="mt-0.5 h-5 w-5 shrink-0" />
        Los docentes pueden pedir cualquier espacio. Los estudiantes solo pueden pedirlo si está habilitado para su carrera.
    </p>
@endif

<div class="grid gap-x-10 lg:grid-cols-2">
    {{-- Columna izquierda: datos generales --}}
    <div>
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
        @endif

        @if ($esEspacio)
            @include('equipos.partes.estado', ['clase' => 'mt-4'])
            @include('equipos.partes.descripcion', ['clase' => 'mt-4'])
        @endif
    </div>

    {{-- Columna derecha --}}
    <div class="mt-4 lg:mt-0">
        @if ($esEspacio)
            {{-- Carreras habilitadas: para estudiantes es una restricción. El buscador filtra la lista;
                 «Seleccionar todas» y «Quitar todas» actúan sobre las carreras visibles. --}}
            <fieldset x-data="{
                    filtro: '',
                    marcadas: 0,
                    contar() { this.marcadas = this.$root.querySelectorAll('input[name=\'carreras[]\']:checked').length },
                    marcar(valor) {
                        this.$root.querySelectorAll('label[data-carrera]').forEach(l => {
                            if (l.style.display !== 'none') l.querySelector('input').checked = valor;
                        });
                        this.contar();
                    },
                 }" x-init="contar()">
                <legend class="block font-medium text-sm text-gray-700">Carreras habilitadas para estudiantes</legend>
                <p class="mt-1 text-xs text-marca-suave">Los estudiantes de estas carreras podrán pedir el espacio. Un espacio puede estar habilitado para varias carreras.</p>
                <p class="mt-2 flex items-start gap-2 rounded-xl bg-marca-crema px-3 py-2 text-xs font-semibold text-marca-bosque ring-1 ring-inset ring-marca-linea">
                    <x-icono nombre="info" class="h-4 w-4 shrink-0" />
                    Si no seleccionas ninguna carrera, el espacio solo podrá ser solicitado por docentes.
                </p>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <button type="button" class="inline-flex items-center gap-1.5 rounded-full border-2 border-marca-oliva bg-white px-4 py-1.5 font-display text-xs font-bold text-marca-oliva transition hover:bg-marca-oliva hover:text-white focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/50" x-on:click="marcar(true)">
                        <x-icono nombre="check" class="h-4 w-4" /><span x-text="filtro ? 'Seleccionar las encontradas' : 'Seleccionar todas'">Seleccionar todas</span>
                    </button>
                    <button type="button" class="enlace text-xs" x-on:click="marcar(false)" x-text="filtro ? 'Quitar las encontradas' : 'Quitar todas'">Quitar todas</button>
                    <span class="text-xs font-bold text-marca-suave" x-cloak x-text="marcadas === 0 ? 'Ninguna seleccionada: solo docentes' : marcadas + (marcadas === 1 ? ' carrera seleccionada' : ' carreras seleccionadas')"></span>
                </div>
                <div class="relative mt-2">
                    <x-icono nombre="buscar" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-marca-gris" />
                    <input type="search" x-model="filtro" placeholder="Buscar carrera" aria-label="Buscar carrera" class="block w-full pl-10 text-sm campo-uts">
                </div>
                <div class="mt-2 grid max-h-64 gap-1 overflow-y-auto rounded-2xl border-2 border-marca-linea p-2 sm:grid-cols-2 lg:max-h-[22rem]">
                    @foreach ($carreras as $carrera)
                        <label data-carrera x-show="@js(Str::lower(Str::ascii($carrera))).includes(filtro.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''))"
                               class="flex cursor-pointer items-start gap-2.5 rounded-xl px-3 py-2 text-sm text-marca-noche hover:bg-marca-crema has-[:checked]:bg-marca-limaclaro has-[:checked]:font-semibold">
                            <input type="checkbox" name="carreras[]" value="{{ $carrera }}" x-on:change="contar()" class="mt-0.5 rounded border-marca-linea text-marca-oliva focus:ring-marca-lima" @checked(in_array($carrera, $elegidas, true))>
                            {{ $carrera }}
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('carreras')" class="mt-2" />
                <x-input-error :messages="$errors->get('carreras.*')" class="mt-2" />
            </fieldset>
        @else
            <div>
                <x-input-label for="solicitable_por" value="¿Quién lo puede pedir?" />
                <select id="solicitable_por" name="solicitable_por" class="mt-1 block w-full campo-uts" required>
                    @foreach (['ambos' => 'Estudiantes y docentes', 'estudiante' => 'Solo estudiantes', 'docente' => 'Solo docentes'] as $valor => $texto)
                        <option value="{{ $valor }}" @selected(old('solicitable_por', $equipo?->solicitable_por ?? 'ambos') === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('solicitable_por')" class="mt-2" />
            </div>

            @include('equipos.partes.estado', ['clase' => 'mt-4'])
            @include('equipos.partes.descripcion', ['clase' => 'mt-4'])
        @endif
    </div>
</div>
