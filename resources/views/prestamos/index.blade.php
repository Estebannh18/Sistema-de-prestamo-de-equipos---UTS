<x-modulo titulo="Préstamos">
    <table class="tabla">
        <thead>
            <tr>
                <th>Equipo</th>
                <th>Solicitante</th>
                <th>Estado</th>
                <th>Devolver antes de</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($prestamos as $prestamo)
                <tr>
                    <td>{{ $prestamo->equipo->nombre }}</td>
                    <td>{{ $prestamo->solicitante->name }}</td>
                    <td><span class="insignia insignia-{{ $prestamo->estaVencido() ? 'vencido' : $prestamo->estado }}">{{ $prestamo->estaVencido() ? 'vencido' : $prestamo->estado }}</span></td>
                    <td>{{ $prestamo->fecha_esperada_devolucion->format('d/m/Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Todavía no hay préstamos.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-modulo>
