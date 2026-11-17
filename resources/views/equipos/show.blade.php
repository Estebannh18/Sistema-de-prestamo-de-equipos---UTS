@php($carreras = $equipo->carrerasRecomendadas())

<x-modulo :titulo="$equipo->nombre" :pendiente="false">
    @if ($equipo->recomendadoPara(Auth::user()))
        <p class="mb-5 inline-flex items-center gap-1.5 rounded-full bg-marca-lima px-3 py-1 font-display text-sm font-bold text-marca-noche">
            <x-icono nombre="estrella" class="h-4 w-4" />Recomendado para tu programa
        </p>
    @endif

    <dl class="grid max-w-2xl gap-x-6 gap-y-3 sm:grid-cols-[max-content_1fr]">
        <dt class="font-semibold text-marca-noche">Código</dt>
        <dd>{{ $equipo->codigo_inventario }}</dd>
        <dt class="font-semibold text-marca-noche">Categoría</dt>
        <dd>{{ $equipo->categoria->nombre }}</dd>
        <dt class="font-semibold text-marca-noche">Estado</dt>
        <dd><span class="insignia insignia-{{ $equipo->estado }}">{{ $equipo->estado }}</span></dd>
        <dt class="font-semibold text-marca-noche">Lo puede pedir</dt>
        <dd>{{ $equipo->quienPuedePedir() }}</dd>
        @if ($equipo->esEspacio())
            @if ($equipo->ubicacion)
                <dt class="font-semibold text-marca-noche">Ubicación</dt>
                <dd>{{ $equipo->ubicacion }}</dd>
            @endif
            @if ($equipo->capacidad)
                <dt class="font-semibold text-marca-noche">Capacidad</dt>
                <dd>{{ $equipo->capacidad }} personas</dd>
            @endif
            @if ($carreras->isNotEmpty())
                <dt class="font-semibold text-marca-noche">Recomendado para</dt>
                <dd>
                    <ul class="flex flex-wrap gap-1.5">
                        @foreach ($carreras as $carrera)
                            <li class="rounded-full bg-marca-limaclaro px-2.5 py-0.5 text-xs font-semibold text-marca-bosque">{{ $carrera }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-1.5 text-xs text-marca-suave">Es una recomendación: cualquier docente lo puede pedir.</p>
                </dd>
            @endif
        @else
            @if ($equipo->marca)
                <dt class="font-semibold text-marca-noche">Marca</dt>
                <dd>{{ $equipo->marca }}</dd>
            @endif
            @if ($equipo->modelo)
                <dt class="font-semibold text-marca-noche">Modelo</dt>
                <dd>{{ $equipo->modelo }}</dd>
            @endif
        @endif
        @if ($equipo->descripcion)
            <dt class="font-semibold text-marca-noche">Descripción</dt>
            <dd>{{ $equipo->descripcion }}</dd>
        @endif
    </dl>

    <p class="mt-6"><a class="enlace" href="{{ route('equipos.index') }}">Volver al listado</a></p>
</x-modulo>
