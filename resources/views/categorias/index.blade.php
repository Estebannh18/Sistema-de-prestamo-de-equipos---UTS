<x-modulo titulo="Categorías" :pendiente="false">
    @if (session('status'))
        <p class="aviso-ok">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="aviso-error">{{ session('error') }}</p>
    @endif

    <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
        <form method="GET" action="{{ route('categorias.index') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <x-input-label for="buscar" value="Buscar por nombre" />
                <x-text-input id="buscar" name="buscar" type="search" class="mt-1 block w-64 max-w-full" :value="$buscar" />
            </div>
            <x-secondary-button type="submit">Filtrar</x-secondary-button>
            @if ($buscar !== '')
                <a class="enlace text-sm" href="{{ route('categorias.index') }}">Limpiar</a>
            @endif
        </form>

        <a class="boton-principal text-sm" href="{{ route('categorias.create') }}">Crear categoría</a>
    </div>

    <table class="tabla">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Equipos</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($categorias as $categoria)
                <tr>
                    <td>{{ $categoria->nombre }}</td>
                    <td>{{ $categoria->descripcion }}</td>
                    <td><a class="enlace" href="{{ route('categorias.show', $categoria) }}">{{ $categoria->equipos_count }}</a></td>
                    <td class="whitespace-nowrap">
                        <a class="enlace" href="{{ route('categorias.edit', $categoria) }}">Editar</a>
                        <form method="POST" action="{{ route('categorias.destroy', $categoria) }}" class="inline ms-2" onsubmit="return confirm('¿Eliminar esta categoría?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="font-medium text-red-700 underline underline-offset-4 hover:text-red-900">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4">No hay categorías para mostrar.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-modulo>
