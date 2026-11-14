@php
    $usuario = Auth::user();
    $hayFiltros = $buscar !== '' || $categoriaActual || $estadoActual;
    $pestanas = [null => 'Todos', 'equipo' => 'Equipos', 'espacio' => 'Espacios'];
    $estados = [
        'disponible' => ['Disponibles', 'bg-emerald-500'],
        'prestado' => ['Prestados', 'bg-sky-500'],
        'mantenimiento' => ['En mantenimiento', 'bg-amber-400'],
    ];
    $botonIcono = 'flex h-9 w-9 items-center justify-center rounded-full transition focus:outline-none focus-visible:ring-4';
@endphp

<x-modulo titulo="Equipos y salones" :pendiente="false">
    @if (session('status'))
        <p class="aviso-ok">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="aviso-error">{{ session('error') }}</p>
    @endif

    {{-- Pestañas por tipo y, para el administrador, los botones de crear --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <nav class="inline-flex rounded-full bg-marca-crema p-1 ring-1 ring-inset ring-marca-linea" aria-label="Tipo">
            @foreach ($pestanas as $valor => $etiqueta)
                @php($activa = $tipoActual === ($valor ?: null))
                <a href="{{ request()->fullUrlWithQuery(['tipo' => $valor ?: null, 'categoria' => null]) }}"
                   @if ($activa) aria-current="page" @endif
                   class="rounded-full px-5 py-2 font-display text-sm font-bold transition focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/60 {{ $activa ? 'bg-marca-bosque text-white shadow' : 'text-marca-bosque hover:bg-white' }}">
                    {{ $etiqueta }}
                </a>
            @endforeach
        </nav>

        @if ($usuario->esAdministrador())
            <div class="flex flex-wrap gap-3">
                <a class="boton-principal text-sm" href="{{ route('equipos.create') }}"><x-icono nombre="mas" class="h-4 w-4" />Crear equipo</a>
                @if ($salones)
                    <a class="boton-principal text-sm" href="{{ route('equipos.create', ['categoria' => $salones]) }}"><x-icono nombre="salon" class="h-4 w-4" />Crear salón</a>
                @endif
            </div>
        @endif
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('equipos.index') }}" class="mt-6 flex flex-wrap items-end gap-3 rounded-3xl bg-marca-crema p-4 ring-1 ring-inset ring-marca-linea">
        @if ($tipoActual)
            <input type="hidden" name="tipo" value="{{ $tipoActual }}">
        @endif
        @if ($estadoActual)
            <input type="hidden" name="estado" value="{{ $estadoActual }}">
        @endif
        <div class="min-w-[14rem] flex-1">
            <x-input-label for="buscar" value="Buscar" />
            <div class="relative mt-1">
                <x-icono nombre="buscar" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-marca-gris" />
                <x-text-input id="buscar" name="buscar" type="search" class="block w-full pl-10" :value="$buscar" placeholder="Nombre, código, marca o modelo" />
            </div>
        </div>
        <div>
            <x-input-label for="categoria" value="Categoría" />
            <select id="categoria" name="categoria" class="mt-1 block w-52 campo-uts">
                <option value="">Todas</option>
                @foreach ($categorias as $opcion)
                    <option value="{{ $opcion->id }}" @selected((string) $categoriaActual === (string) $opcion->id)>{{ $opcion->nombre }}</option>
                @endforeach
            </select>
        </div>
        <x-secondary-button type="submit"><x-icono nombre="filtro" class="me-1.5 h-4 w-4" />Filtrar</x-secondary-button>
        @if ($hayFiltros)
            <a class="enlace self-end pb-3 text-sm" href="{{ route('equipos.index', array_filter(['tipo' => $tipoActual])) }}">Limpiar</a>
        @endif
    </form>

    {{-- Resumen: el personal ve cuántos hay en cada estado y puede filtrar con un clic --}}
    @if ($esPersonal)
        <div class="mt-4 flex flex-wrap items-center gap-2" aria-label="Filtrar por estado">
            @foreach ($estados as $valor => [$etiqueta, $color])
                @php($activo = $estadoActual === $valor)
                <a href="{{ request()->fullUrlWithQuery(['estado' => $activo ? null : $valor]) }}"
                   @if ($activo) aria-current="true" @endif
                   class="inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-bold transition focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/60 {{ $activo ? 'bg-marca-noche text-white' : 'bg-white text-marca-noche ring-1 ring-inset ring-marca-linea hover:ring-marca-oliva' }}">
                    <span class="h-2 w-2 rounded-full {{ $color }}" aria-hidden="true"></span>
                    {{ $etiqueta }}
                    <span class="tabular-nums {{ $activo ? 'text-marca-lima' : 'text-marca-suave' }}">{{ $porEstado[$valor] ?? 0 }}</span>
                </a>
            @endforeach
        </div>
    @else
        <p class="mt-4 flex items-center gap-2 text-sm text-marca-suave">
            <x-icono nombre="info" class="h-4 w-4 shrink-0" />
            Ves los equipos y espacios disponibles que tu rol puede pedir.
        </p>
    @endif

    <p class="mt-6 text-sm font-semibold tabular-nums text-marca-suave" role="status">
        {{ $total }} {{ $total === 1 ? 'resultado' : 'resultados' }}
    </p>

    @forelse ($grupos as $equipos)
        @php($categoria = $equipos->first()->categoria)

        <section class="mt-6" aria-labelledby="categoria-{{ $categoria->id }}">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-marca-linea pb-3">
                <h2 id="categoria-{{ $categoria->id }}" class="flex items-center gap-3 font-display text-lg font-extrabold text-marca-noche">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-marca-limaclaro text-marca-bosque">
                        <x-icono :nombre="$categoria->iconoVisible()" class="h-5 w-5" />
                    </span>
                    {{ $categoria->nombre }}
                    <span class="text-sm font-bold tabular-nums text-marca-suave">{{ $equipos->count() }}</span>
                </h2>
                @unless ($categoriaActual)
                    <a class="enlace text-xs" href="{{ request()->fullUrlWithQuery(['categoria' => $categoria->id]) }}">Ver solo esta categoría</a>
                @endunless
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($equipos as $equipo)
                    <article class="flex flex-col rounded-3xl border border-marca-linea bg-white p-5 transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_14px_30px_-16px_rgba(11,61,42,0.45)]">
                        <div class="flex items-center justify-between gap-3">
                            <span class="rounded-lg bg-marca-crema px-2 py-0.5 font-mono text-xs font-semibold text-marca-suave ring-1 ring-inset ring-marca-linea">{{ $equipo->codigo_inventario }}</span>
                            <span class="insignia insignia-{{ $equipo->estado }}">{{ $equipo->estado }}</span>
                        </div>

                        <h3 class="mt-3 font-display text-base font-extrabold leading-snug text-marca-noche">
                            <a href="{{ route('equipos.show', $equipo) }}" class="hover:text-marca-oliva focus:outline-none focus-visible:underline">{{ $equipo->nombre }}</a>
                        </h3>
                        @if ($equipo->marca || $equipo->modelo)
                            <p class="text-sm text-marca-suave">{{ collect([$equipo->marca, $equipo->modelo])->filter()->implode(' · ') }}</p>
                        @endif
                        @if ($equipo->descripcion)
                            <p class="mt-2 line-clamp-2 text-sm text-marca-noche/80">{{ $equipo->descripcion }}</p>
                        @endif

                        <div class="mt-auto pt-4">
                        <div class="flex items-center justify-between gap-3 border-t border-marca-linea pt-4">
                            <span class="flex items-center gap-1.5 text-xs font-semibold text-marca-suave">
                                <x-icono nombre="usuarios" class="h-4 w-4" />
                                {{ $equipo->quienPuedePedir() }}
                            </span>

                            @if ($esPersonal)
                                <div class="flex items-center gap-1">
                                    <x-tooltip texto="Ver detalle" alinear="derecha">
                                        <a href="{{ route('equipos.show', $equipo) }}" aria-label="Ver {{ $equipo->nombre }}" class="{{ $botonIcono }} text-marca-oliva hover:bg-marca-limaclaro focus-visible:ring-marca-lima/60">
                                            <x-icono nombre="ver" class="h-5 w-5" />
                                        </a>
                                    </x-tooltip>
                                    @if ($usuario->esAdministrador())
                                        <x-tooltip texto="Editar" alinear="derecha">
                                            <a href="{{ route('equipos.edit', $equipo) }}" aria-label="Editar {{ $equipo->nombre }}" class="{{ $botonIcono }} text-marca-oliva hover:bg-marca-limaclaro focus-visible:ring-marca-lima/60">
                                                <x-icono nombre="editar" class="h-5 w-5" />
                                            </a>
                                        </x-tooltip>
                                        @if ($equipo->prestamos_count > 0)
                                            <x-tooltip texto="Tiene préstamos: no se puede eliminar" alinear="derecha">
                                                <span role="button" aria-disabled="true" tabindex="0" aria-label="No se puede eliminar {{ $equipo->nombre }} porque tiene préstamos"
                                                      class="{{ $botonIcono }} cursor-not-allowed text-marca-gris opacity-50">
                                                    <x-icono nombre="eliminar" class="h-5 w-5" />
                                                </span>
                                            </x-tooltip>
                                        @else
                                            <form method="POST" action="{{ route('equipos.destroy', $equipo) }}" onsubmit="return confirm('¿Eliminar {{ e($equipo->nombre) }} ({{ $equipo->codigo_inventario }})?');">
                                                @csrf
                                                @method('DELETE')
                                                <x-tooltip texto="Eliminar" alinear="derecha">
                                                    <button type="submit" aria-label="Eliminar {{ $equipo->nombre }}" class="{{ $botonIcono }} text-rose-700 hover:bg-rose-50 focus-visible:ring-rose-200">
                                                        <x-icono nombre="eliminar" class="h-5 w-5" />
                                                    </button>
                                                </x-tooltip>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            @else
                                {{-- La solicitud recibe el equipo elegido; el formulario lo usará cuando se complete el módulo de préstamos. --}}
                                <a href="{{ route('prestamos.create', ['equipo' => $equipo->id]) }}" class="boton-principal !px-4 !py-1.5 text-xs">Solicitar</a>
                            @endif
                        </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @empty
        <div class="mt-6 rounded-3xl border-2 border-dashed border-marca-linea px-6 py-12 text-center">
            <x-icono nombre="buscar" class="mx-auto h-8 w-8 text-marca-gris" />
            <p class="mt-3 font-display font-bold text-marca-noche">No hay equipos para mostrar.</p>
            @if ($hayFiltros)
                <p class="mt-1 text-sm text-marca-suave">Prueba con otra búsqueda o <a class="enlace" href="{{ route('equipos.index', array_filter(['tipo' => $tipoActual])) }}">limpia los filtros</a>.</p>
            @endif
        </div>
    @endforelse
</x-modulo>
