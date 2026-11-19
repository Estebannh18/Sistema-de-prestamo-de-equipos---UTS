{{-- Paso 4: cuál. Filtros por categoría, búsqueda y «solo con horarios libres»; cada resultado dice cuántos bloques libres tiene ese día. --}}
@php
    $chip = 'shrink-0 rounded-full px-4 py-1.5 text-sm font-bold transition focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/60';
    $titulo = $buscar !== '' ? 'Resultados para «'.$buscar.'»' : ($categoria ? $categoria->nombre : ($tipo === 'espacio' ? 'Todos los espacios' : 'Todos los equipos'));
@endphp
<h2 class="mt-1 font-display text-2xl font-extrabold text-marca-noche">Elige el {{ $nombreTipo }}</h2>
<p class="mt-1 text-sm text-marca-suave">
    {{ $titulo }} · {{ $resultados->total() }} {{ $resultados->total() === 1 ? 'resultado' : 'resultados' }} ·
    disponibilidad del {{ $fecha->locale('es')->isoFormat('dddd D [de] MMMM') }}
</p>

{{-- Filtros --}}
<div class="mt-5 space-y-3 rounded-3xl bg-marca-crema/70 p-4 ring-1 ring-inset ring-marca-linea">
    @include('prestamos.asistente.buscador', ['conCategoria' => true])

    <div class="flex flex-wrap items-center gap-2" aria-label="Categoría">
        <a href="{{ $url(['categoria' => 'todas']) }}" @class([$chip, 'bg-marca-bosque text-white shadow' => $todas || ! $categoria, 'bg-white text-marca-bosque ring-1 ring-inset ring-marca-linea hover:ring-marca-lima' => $categoria])>Todas</a>
        @foreach ($categorias as $opcion)
            @php $activa = $categoria?->is($opcion); @endphp
            <a href="{{ $url(['categoria' => $opcion->id]) }}" @if ($activa) aria-current="true" @endif
               @class([$chip, 'inline-flex items-center gap-1.5', 'bg-marca-bosque text-white shadow' => $activa, 'bg-white text-marca-bosque ring-1 ring-inset ring-marca-linea hover:ring-marca-lima' => ! $activa])>
                <x-icono :nombre="$opcion->iconoVisible()" class="h-4 w-4" />{{ $opcion->nombre }}
            </a>
        @endforeach
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ $url(['libres' => $soloLibres ? null : 1, 'page' => null]) }}" role="switch" aria-checked="{{ $soloLibres ? 'true' : 'false' }}"
           class="inline-flex items-center gap-2.5 text-sm font-bold text-marca-noche focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/60 rounded-full">
            <span class="relative inline-flex h-6 w-11 items-center rounded-full transition {{ $soloLibres ? 'bg-marca-bosque' : 'bg-marca-linea' }}">
                <span class="inline-block h-5 w-5 rounded-full bg-white shadow transition {{ $soloLibres ? 'translate-x-5' : 'translate-x-0.5' }}"></span>
            </span>
            Solo con horarios libres ese día
        </a>
        @if ($buscar !== '')
            <a href="{{ $url(['q' => null]) }}" class="enlace inline-flex items-center gap-1 text-sm"><x-icono nombre="cerrar" class="h-4 w-4" />Quitar la búsqueda</a>
        @endif
    </div>
</div>

{{-- Resultados --}}
@if ($resultados->isEmpty())
    <div class="mt-6 rounded-3xl border-2 border-dashed border-marca-linea px-6 py-12 text-center">
        <x-icono nombre="buscar" class="mx-auto h-8 w-8 text-marca-gris" />
        <p class="mt-3 font-display font-bold text-marca-noche">No encontramos {{ $tipo === 'espacio' ? 'espacios' : 'equipos' }} con esos filtros.</p>
        <p class="mt-1 text-sm text-marca-suave">
            @if ($soloLibres)
                Prueba <a class="enlace" href="{{ $url(['libres' => null]) }}">mostrando también los ocupados</a> o <a class="enlace" href="{{ $irA['dia'] }}">elige otro día</a>.
            @elseif ($buscar !== '')
                Revisa lo que escribiste o <a class="enlace" href="{{ $url(['q' => null, 'categoria' => 'todas']) }}">mira todas las opciones</a>.
            @else
                Prueba con otra categoría.
            @endif
        </p>
    </div>
@else
    <ul class="mt-5 grid gap-3 xl:grid-cols-2">
        @foreach ($resultados as ['equipo' => $opcion, 'libres' => $libres, 'delDia' => $delDia])
            @php $sePuede = $libres > 0; @endphp
            <li @class([
                'flex items-center gap-4 rounded-2xl border-2 bg-white p-4 transition',
                'border-marca-linea hover:border-marca-lima hover:shadow-[0_12px_24px_-16px_rgba(11,61,42,0.5)]' => $sePuede,
                'border-marca-linea/70 opacity-75' => ! $sePuede,
            ])>
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $sePuede ? 'bg-marca-limaclaro text-marca-oliva ring-2 ring-marca-lima' : 'bg-marca-crema text-marca-gris ring-1 ring-marca-linea' }}">
                    <x-icono :nombre="$opcion->categoria->iconoVisible()" class="h-6 w-6" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-x-2 text-xs font-bold text-marca-oliva">
                        {{ $opcion->categoria->nombre }}
                        <span class="rounded-md bg-marca-crema px-1.5 font-mono font-semibold text-marca-suave ring-1 ring-inset ring-marca-linea">{{ $opcion->codigo_inventario }}</span>
                    </p>
                    <h3 class="mt-0.5 font-display font-extrabold leading-snug text-marca-noche">{{ $opcion->nombre }}</h3>
                    <p class="text-xs text-marca-suave">
                        @if ($opcion->esEspacio())
                            {{ collect([$opcion->ubicacion, $opcion->capacidad ? $opcion->capacidad.' personas' : null])->filter()->implode(' · ') }}
                        @else
                            {{ collect([$opcion->marca, $opcion->modelo])->filter()->implode(' · ') }}
                        @endif
                    </p>
                    <p class="mt-1 text-xs font-bold">
                        @if ($sePuede)
                            <span class="inline-flex items-center gap-1 text-emerald-700"><span class="h-2 w-2 rounded-full bg-emerald-600" aria-hidden="true"></span>{{ $libres }} {{ $libres === 1 ? 'bloque libre' : 'bloques libres' }}</span>
                        @elseif ($opcion->horarios_count === 0)
                            <span class="text-marca-suave">Sin horarios</span>
                        @elseif ($delDia === 0)
                            <span class="text-marca-suave">No se presta este día</span>
                        @else
                            <span class="text-rose-700">Sin bloques libres este día</span>
                        @endif
                    </p>
                </div>
                @if ($sePuede)
                    <a href="{{ $url(['equipo' => $opcion->id]) }}" class="boton-principal shrink-0 !px-4 !py-1.5 text-xs">Elegir</a>
                @endif
            </li>
        @endforeach
    </ul>

    @if ($resultados->hasPages())
        <div class="mt-6">{{ $resultados->links() }}</div>
    @endif
@endif
