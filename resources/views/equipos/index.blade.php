<x-modulo titulo="Equipos y salones" :pendiente="false">
    @if (session('status'))
        <p class="aviso-ok">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="aviso-error">{{ session('error') }}</p>
    @endif

    @php($esPersonal = Auth::user()->esAdministrador() || Auth::user()->esFuncionario())

    @if (Auth::user()->esAdministrador())
        <div class="mb-5 flex flex-wrap gap-3">
            <a class="boton-principal text-sm" href="{{ route('equipos.create') }}">Crear equipo</a>
            @if ($salones)
                <a class="boton-principal text-sm" href="{{ route('equipos.create', ['categoria' => $salones]) }}">Crear salón</a>
            @endif
        </div>
    @endif

    <form method="GET" action="{{ route('equipos.index') }}" class="mb-5 flex flex-wrap items-end gap-3">
        <div>
            <x-input-label for="buscar" value="Nombre o código" />
            <x-text-input id="buscar" name="buscar" type="search" class="mt-1 block w-56 max-w-full" :value="$buscar" />
        </div>
        <div>
            <x-input-label for="categoria" value="Categoría" />
            <select id="categoria" name="categoria" class="mt-1 block w-48 border-marca-linea focus:border-marca-oliva focus:ring-marca-oliva rounded-md shadow-sm">
                <option value="">Todas</option>
                @foreach ($categorias as $opcion)
                    <option value="{{ $opcion->id }}" @selected((string) $categoriaActual === (string) $opcion->id)>{{ $opcion->nombre }}</option>
                @endforeach
            </select>
        </div>
        @if ($esPersonal)
            <div>
                <x-input-label for="estado" value="Estado" />
                <select id="estado" name="estado" class="mt-1 block w-44 border-marca-linea focus:border-marca-oliva focus:ring-marca-oliva rounded-md shadow-sm">
                    <option value="">Todos</option>
                    @foreach (['disponible', 'prestado', 'mantenimiento'] as $opcion)
                        <option value="{{ $opcion }}" @selected($estadoActual === $opcion)>{{ $opcion }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <x-secondary-button type="submit">Filtrar</x-secondary-button>
        @if ($buscar !== '' || $categoriaActual || $estadoActual)
            <a class="enlace text-sm" href="{{ route('equipos.index') }}">Limpiar</a>
        @endif
    </form>

    <table class="tabla">
        <thead>
            <tr>
                <th>Código</th>
                <th>Nombre</th>
                <th>Categoría</th>
                <th>Estado</th>
                <th>Lo puede pedir</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($equipos as $equipo)
                <tr>
                    <td>{{ $equipo->codigo_inventario }}</td>
                    <td>{{ $equipo->nombre }}</td>
                    <td>{{ $equipo->categoria->nombre }}</td>
                    <td><span class="insignia">{{ $equipo->estado }}</span></td>
                    <td><span class="insignia">{{ $equipo->solicitable_por }}</span></td>
                    <td class="whitespace-nowrap">
                        <a class="enlace" href="{{ route('equipos.show', $equipo) }}">Ver</a>
                        @if (Auth::user()->esAdministrador())
                            <a class="enlace ms-2" href="{{ route('equipos.edit', $equipo) }}">Editar</a>
                            <form method="POST" action="{{ route('equipos.destroy', $equipo) }}" class="inline ms-2" onsubmit="return confirm('¿Eliminar este equipo?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="font-medium text-red-700 underline underline-offset-4 hover:text-red-900">Eliminar</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">No hay equipos para mostrar.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-modulo>
