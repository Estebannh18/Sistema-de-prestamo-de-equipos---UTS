@php
    // "1 disponible", "3 disponibles"...
    $contar = fn (int $n, string $singular, string $plural) => $n.' '.($n === 1 ? $singular : $plural);
    $porcentaje = $totales['equipos'] > 0 ? round($totales['disponibles'] * 100 / $totales['equipos']) : 0;
@endphp

<x-modulo titulo="Categorías" :pendiente="false">
    @if (session('status'))
        <p class="aviso-ok">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="aviso-error">{{ session('error') }}</p>
    @endif

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
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

        <a class="boton-principal text-sm" href="{{ route('categorias.create') }}"><x-icono nombre="mas" class="h-4 w-4" />Crear categoría</a>
    </div>

    {{-- Resumen --}}
    <dl class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl bg-marca-crema px-5 py-4 ring-1 ring-inset ring-marca-linea">
            <dt class="font-display text-xs font-bold uppercase tracking-wider text-marca-suave">Categorías</dt>
            <dd class="mt-1 font-display text-3xl font-black tabular-nums text-marca-noche">{{ $totales['categorias'] }}</dd>
        </div>
        <div class="rounded-2xl bg-marca-crema px-5 py-4 ring-1 ring-inset ring-marca-linea">
            <dt class="font-display text-xs font-bold uppercase tracking-wider text-marca-suave">Equipos y espacios</dt>
            <dd class="mt-1 font-display text-3xl font-black tabular-nums text-marca-noche">{{ $totales['equipos'] }}</dd>
        </div>
        <div class="rounded-2xl bg-marca-limaclaro px-5 py-4 ring-1 ring-inset ring-[#dfe78f]">
            <dt class="font-display text-xs font-bold uppercase tracking-wider text-marca-bosque">Disponibles ahora</dt>
            <dd class="mt-1 flex items-baseline gap-2 font-display text-3xl font-black tabular-nums text-marca-noche">
                {{ $totales['disponibles'] }}
                <span class="text-sm font-bold text-marca-bosque">{{ $porcentaje }} %</span>
            </dd>
        </div>
    </dl>

    @if ($totales['categorias'] === 0)
        <div class="mt-8 rounded-3xl border-2 border-dashed border-marca-linea px-6 py-12 text-center">
            <x-icono nombre="categorias" class="mx-auto h-8 w-8 text-marca-gris" />
            <p class="mt-3 font-display font-bold text-marca-noche">No hay categorías para mostrar.</p>
            @if ($buscar !== '')
                <p class="mt-1 text-sm text-marca-suave">Ninguna coincide con «{{ $buscar }}».</p>
            @endif
        </div>
    @endif

    @foreach ($grupos as $titulo => $categorias)
        @continue($categorias->isEmpty())

        <section class="mt-10" aria-labelledby="grupo-{{ Str::slug($titulo) }}">
            <h2 id="grupo-{{ Str::slug($titulo) }}" class="flex items-center gap-3 font-display text-xl font-extrabold text-marca-noche">
                <span class="h-6 w-1.5 rounded-full bg-marca-lima" aria-hidden="true"></span>
                {{ $titulo }}
                <span class="text-sm font-bold text-marca-suave">{{ $categorias->count() }}</span>
            </h2>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($categorias as $categoria)
                    @php
                        $total = $categoria->equipos_count;
                        $unidad = $categoria->esEspacio() ? ['espacio', 'espacios'] : ['equipo', 'equipos'];
                        $segmentos = [
                            ['bg-emerald-500', $categoria->disponibles_count, $contar($categoria->disponibles_count, 'disponible', 'disponibles')],
                            ['bg-sky-500', $categoria->prestados_count, $contar($categoria->prestados_count, 'prestado', 'prestados')],
                            ['bg-amber-400', $categoria->mantenimiento_count, $categoria->mantenimiento_count.' en mantenimiento'],
                        ];
                    @endphp

                    <article class="flex flex-col rounded-3xl border border-marca-linea bg-white p-5 transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_14px_30px_-16px_rgba(11,61,42,0.45)]">
                        <div class="flex items-start gap-4">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-marca-limaclaro text-marca-bosque">
                                <x-icono :nombre="$categoria->iconoVisible()" class="h-6 w-6" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-display text-lg font-extrabold leading-tight text-marca-noche">{{ $categoria->nombre }}</h3>
                                <p class="mt-0.5 text-sm font-semibold tabular-nums text-marca-suave">{{ $contar($total, ...$unidad) }}</p>
                            </div>
                        </div>

                        @if ($categoria->descripcion)
                            <p class="mt-3 text-sm text-marca-noche/80">{{ $categoria->descripcion }}</p>
                        @else
                            <p class="mt-3 text-sm italic text-marca-gris">Sin descripción.</p>
                        @endif

                        <div class="mt-auto pt-5">
                            @if ($total > 0)
                                <div class="flex h-2.5 overflow-hidden rounded-full bg-marca-crema" role="img"
                                     aria-label="{{ collect($segmentos)->pluck(2)->implode(', ') }}">
                                    @foreach ($segmentos as [$color, $cantidad])
                                        @if ($cantidad > 0)
                                            <span class="{{ $color }}" style="width: {{ $cantidad * 100 / $total }}%"></span>
                                        @endif
                                    @endforeach
                                </div>
                                <ul class="mt-2.5 flex flex-wrap gap-x-4 gap-y-1 text-xs font-semibold tabular-nums text-marca-suave">
                                    @foreach ($segmentos as [$color, $cantidad, $texto])
                                        @if ($cantidad > 0)
                                            <li class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full {{ $color }}" aria-hidden="true"></span>{{ $texto }}</li>
                                        @endif
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-xs font-semibold text-marca-suave">Aún no tiene {{ $unidad[1] }} registrados.</p>
                            @endif

                            <div class="mt-4 flex items-center justify-between gap-3 border-t border-marca-linea pt-4">
                                <a class="enlace text-sm" href="{{ route('categorias.show', $categoria) }}">Ver {{ $unidad[1] }}</a>
                                <div class="flex items-center gap-1">
                                    <x-tooltip texto="Editar" alinear="derecha">
                                        <a href="{{ route('categorias.edit', $categoria) }}" aria-label="Editar {{ $categoria->nombre }}"
                                           class="flex h-9 w-9 items-center justify-center rounded-full text-marca-oliva transition hover:bg-marca-limaclaro focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/60">
                                            <x-icono nombre="editar" class="h-5 w-5" />
                                        </a>
                                    </x-tooltip>
                                    @if ($total > 0)
                                        {{-- RF-16: con equipos registrados no se puede eliminar; se explica en vez de fallar al intentarlo. --}}
                                        <x-tooltip texto="Tiene {{ $unidad[1] }}: no se puede eliminar" alinear="derecha">
                                            <span role="button" aria-disabled="true" tabindex="0" aria-label="No se puede eliminar {{ $categoria->nombre }} porque tiene {{ $unidad[1] }}"
                                                  class="flex h-9 w-9 cursor-not-allowed items-center justify-center rounded-full text-marca-gris opacity-50">
                                                <x-icono nombre="eliminar" class="h-5 w-5" />
                                            </span>
                                        </x-tooltip>
                                    @else
                                        <form method="POST" action="{{ route('categorias.destroy', $categoria) }}" onsubmit="return confirm('¿Eliminar la categoría {{ e($categoria->nombre) }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <x-tooltip texto="Eliminar" alinear="derecha">
                                                <button type="submit" aria-label="Eliminar {{ $categoria->nombre }}"
                                                        class="flex h-9 w-9 items-center justify-center rounded-full text-rose-700 transition hover:bg-rose-50 focus:outline-none focus-visible:ring-4 focus-visible:ring-rose-200">
                                                    <x-icono nombre="eliminar" class="h-5 w-5" />
                                                </button>
                                            </x-tooltip>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endforeach
</x-modulo>
