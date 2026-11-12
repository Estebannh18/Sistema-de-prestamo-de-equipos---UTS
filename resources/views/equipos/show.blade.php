<x-modulo :titulo="$equipo->nombre" :pendiente="false">
    <dl class="grid max-w-2xl gap-x-6 gap-y-3 sm:grid-cols-[max-content_1fr]">
        <dt class="font-semibold text-marca-noche">Código</dt>
        <dd>{{ $equipo->codigo_inventario }}</dd>
        <dt class="font-semibold text-marca-noche">Categoría</dt>
        <dd>{{ $equipo->categoria->nombre }}</dd>
        <dt class="font-semibold text-marca-noche">Estado</dt>
        <dd><span class="insignia">{{ $equipo->estado }}</span></dd>
        <dt class="font-semibold text-marca-noche">Lo puede pedir</dt>
        <dd><span class="insignia">{{ $equipo->solicitable_por }}</span></dd>
        @if ($equipo->marca)
            <dt class="font-semibold text-marca-noche">Marca</dt>
            <dd>{{ $equipo->marca }}</dd>
        @endif
        @if ($equipo->modelo)
            <dt class="font-semibold text-marca-noche">Modelo</dt>
            <dd>{{ $equipo->modelo }}</dd>
        @endif
        @if ($equipo->descripcion)
            <dt class="font-semibold text-marca-noche">Descripción</dt>
            <dd>{{ $equipo->descripcion }}</dd>
        @endif
    </dl>

    <p class="mt-6"><a class="enlace" href="{{ route('equipos.index') }}">Volver al listado</a></p>
</x-modulo>
