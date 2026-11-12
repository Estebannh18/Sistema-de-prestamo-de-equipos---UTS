<x-modulo titulo="Solicitud">
    <p class="mb-4">Equipos disponibles que puedes pedir:</p>

    <table class="tabla">
        <thead>
            <tr>
                <th>Código</th>
                <th>Nombre</th>
                <th>Categoría</th>
                <th>Estado</th>
                <th>Lo puede pedir</th>
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
                </tr>
            @empty
                <tr><td colspan="5">No hay equipos para mostrar.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-modulo>
