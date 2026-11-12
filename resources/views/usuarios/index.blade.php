<x-modulo titulo="Usuarios" :pendiente="false">
    @if (session('status'))
        <p class="aviso-ok">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="aviso-error">{{ session('error') }}</p>
    @endif

    <p class="mb-5"><a class="boton-principal text-sm" href="{{ route('usuarios.create') }}">Crear usuario</a></p>

    <table class="tabla">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Correo</th>
                <th>Documento</th>
                <th>Rol</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($usuarios as $usuario)
                <tr>
                    <td>{{ $usuario->name }}</td>
                    <td>{{ $usuario->email }}</td>
                    <td>{{ $usuario->documento }}</td>
                    <td><span class="insignia insignia-{{ $usuario->rol }}">{{ $usuario->rol }}</span></td>
                    <td>
                        <a class="enlace" href="{{ route('usuarios.edit', $usuario) }}">Editar</a>
                        <form method="POST" action="{{ route('usuarios.destroy', $usuario) }}" class="inline ms-2" onsubmit="return confirm('¿Eliminar este usuario?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="font-medium text-red-700 underline underline-offset-4 hover:text-red-900">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-modulo>
