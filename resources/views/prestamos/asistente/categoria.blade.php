{{-- Paso 3: la categoría, o buscar directamente si ya sabe qué necesita. --}}
<h2 class="mt-1 font-display text-2xl font-extrabold text-marca-noche">¿Qué tipo de {{ $nombreTipo }} buscas?</h2>
<p class="mt-1 text-sm text-marca-suave">Escribe lo que necesitas o elige una categoría.</p>

@include('prestamos.asistente.buscador', ['clase' => 'mt-5'])

@if ($categorias->isEmpty())
    <div class="mt-6 rounded-3xl border-2 border-dashed border-marca-linea px-6 py-12 text-center">
        <x-icono nombre="buscar" class="mx-auto h-8 w-8 text-marca-gris" />
        <p class="mt-3 font-display font-bold text-marca-noche">No hay nada disponible para solicitar aquí.</p>
        @if ($tipo === 'espacio')
            <p class="mt-1 text-sm text-marca-suave">Todavía no hay espacios habilitados para tu carrera{{ $usuario->programa ? ' ('.$usuario->programa->nombre.')' : '' }}.</p>
        @endif
    </div>
@else
    <p class="mt-7 text-xs font-bold uppercase tracking-wider text-marca-suave">Categorías</p>
    <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($categorias as $opcion)
            <a href="{{ $url(['categoria' => $opcion->id]) }}" class="group flex items-center gap-4 rounded-2xl border-2 border-marca-linea bg-white p-4 transition duration-200 hover:-translate-y-0.5 hover:border-marca-lima hover:shadow-[0_12px_24px_-16px_rgba(11,61,42,0.5)] focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/60">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-marca-limaclaro text-marca-oliva ring-2 ring-marca-lima transition group-hover:bg-marca-lima group-hover:text-marca-noche">
                    <x-icono :nombre="$opcion->iconoVisible()" class="h-6 w-6" />
                </span>
                <span class="min-w-0">
                    <span class="block font-display font-extrabold leading-snug text-marca-noche">{{ $opcion->nombre }}</span>
                    <span class="block text-xs text-marca-suave">{{ $opcion->opciones_count }} {{ $opcion->opciones_count === 1 ? 'opción' : 'opciones' }}</span>
                </span>
                <x-icono nombre="derecha" class="ml-auto h-5 w-5 shrink-0 text-marca-gris transition group-hover:translate-x-0.5 group-hover:text-marca-oliva" />
            </a>
        @endforeach
        <a href="{{ $url(['categoria' => 'todas']) }}" class="group flex items-center gap-4 rounded-2xl border-2 border-dashed border-marca-linea bg-marca-crema/50 p-4 transition hover:border-marca-lima hover:bg-white focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/60">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white text-marca-bosque ring-1 ring-marca-linea">
                <x-icono nombre="menu" class="h-6 w-6" />
            </span>
            <span class="font-display font-extrabold text-marca-noche">Ver {{ $tipo === 'espacio' ? 'todos los espacios' : 'todos los equipos' }}</span>
            <x-icono nombre="derecha" class="ml-auto h-5 w-5 shrink-0 text-marca-gris transition group-hover:text-marca-oliva" />
        </a>
    </div>
@endif
