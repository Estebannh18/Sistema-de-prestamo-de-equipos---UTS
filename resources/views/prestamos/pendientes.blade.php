{{--
    Solicitudes por aprobar (funcionario y administrador), con búsqueda, filtros y orden.
    Los filtros viajan en la dirección: $filtros tiene solo los que están puestos.
--}}
@php
    use App\Http\Controllers\PrestamoController;

    $pestanas = [null => 'Todas', 'espacio' => 'Espacios', 'equipo' => 'Equipos'];
    $perfiles = ['estudiante' => 'Estudiantes', 'docente' => 'Docentes'];
    $ordenes = PrestamoController::ORDENES_PENDIENTES;
    $orden = $filtros['orden'] ?? 'antiguas';
    // Filtros y página actuales, para volver a la misma lista después de aprobar o rechazar.
    $volver = http_build_query($filtros + ($pendientes->currentPage() > 1 ? ['page' => $pendientes->currentPage()] : []));
    $cuando = fn ($fecha) => $fecha->locale('es')->isoFormat('ddd D/MM/YYYY, h:mm a');

    // Dirección de la lista con los filtros actuales más (o menos) los cambios. Al cambiar de filtro se vuelve a la página 1.
    $url = fn (array $cambios = []) => route('prestamos.pendientes', array_filter(
        array_merge($filtros, $cambios), fn ($valor) => $valor !== null && $valor !== ''));

    // Filtros puestos, para mostrarlos como etiquetas que se pueden quitar (el orden no cuenta como filtro).
    $etiquetas = array_filter([
        'buscar' => isset($filtros['buscar']) ? '«'.$filtros['buscar'].'»' : null,
        'tipo' => isset($filtros['tipo']) ? $pestanas[$filtros['tipo']] : null,
        'perfil' => isset($filtros['perfil']) ? $perfiles[$filtros['perfil']] : null,
        'categoria' => $categoriaActual?->nombre,
        'programa' => $programaActual ? $programaActual->nombre.' · '.$programaActual->sede : null,
        'fecha' => isset($filtros['fecha']) ? ucfirst(\Illuminate\Support\Carbon::parse($filtros['fecha'])->locale('es')->isoFormat('dddd D [de] MMMM')) : null,
        'choques' => isset($filtros['choques']) ? 'Con otra solicitud del mismo bloque' : null,
    ]);
@endphp

<x-modulo titulo="Solicitudes por aprobar" :pendiente="false">
    @if (session('status'))
        <p class="aviso-ok"><x-icono nombre="check" class="h-5 w-5 shrink-0" />{{ session('status') }}</p>
    @endif
    @if ($errors->has('solicitud'))
        <p class="aviso-error"><x-icono nombre="alerta" class="h-5 w-5 shrink-0" />{{ $errors->first('solicitud') }}</p>
    @endif

    {{-- Tipo: pestañas. Al cambiar de tipo se quita la categoría, que es de un solo tipo. --}}
    <nav class="inline-flex rounded-full bg-marca-crema p-1 ring-1 ring-inset ring-marca-linea" aria-label="Tipo">
        @foreach ($pestanas as $valor => $etiqueta)
            @php($activa = ($filtros['tipo'] ?? null) === ($valor ?: null))
            <a href="{{ $url(['tipo' => $valor ?: null, 'categoria' => null]) }}" @if ($activa) aria-current="page" @endif
               class="rounded-full px-5 py-2 font-display text-sm font-bold transition focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/60 {{ $activa ? 'bg-marca-bosque text-white shadow' : 'text-marca-bosque hover:bg-white' }}">
                {{ $etiqueta }}
            </a>
        @endforeach
    </nav>

    {{-- Búsqueda, filtros y orden. Las listas se aplican al cambiarlas; la búsqueda, con Enter o «Filtrar». --}}
    <form method="GET" action="{{ route('prestamos.pendientes') }}" class="mt-4 rounded-3xl bg-marca-crema/60 p-4 ring-1 ring-inset ring-marca-linea sm:p-5"
          x-data x-on:change="if ($event.target.name !== 'buscar') $el.requestSubmit()">
        @isset($filtros['tipo'])<input type="hidden" name="tipo" value="{{ $filtros['tipo'] }}">@endisset

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2">
                <x-input-label for="buscar" value="Buscar" />
                <div class="relative mt-1">
                    <x-icono nombre="buscar" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-marca-suave" />
                    <input id="buscar" name="buscar" type="search" value="{{ $filtros['buscar'] ?? '' }}" maxlength="80"
                           placeholder="Solicitante, documento, espacio o código"
                           class="campo-uts block w-full pl-10 text-sm placeholder:text-marca-gris">
                </div>
            </div>
            <div>
                <x-input-label for="perfil" value="Perfil" />
                <select id="perfil" name="perfil" class="campo-uts mt-1 block w-full text-sm">
                    <option value="">Todos</option>
                    @foreach ($perfiles as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(($filtros['perfil'] ?? null) === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="categoria" value="Categoría" />
                <select id="categoria" name="categoria" class="campo-uts mt-1 block w-full text-sm">
                    <option value="">Todas</option>
                    @foreach ($categorias as $tipoCategoria => $lista)
                        <optgroup label="{{ $tipoCategoria === 'espacio' ? 'Espacios' : 'Equipos' }}">
                            @foreach ($lista as $categoria)
                                <option value="{{ $categoria->id }}" @selected($categoriaActual?->id === $categoria->id)>{{ $categoria->nombre }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <x-input-label for="programa" value="Carrera del solicitante" />
                <select id="programa" name="programa" class="campo-uts mt-1 block w-full text-sm">
                    <option value="">Todas</option>
                    @foreach ($programas as $sede => $programasSede)
                        <optgroup label="{{ $sede }}">
                            @foreach ($programasSede as $programa)
                                <option value="{{ $programa->id }}" @selected($programaActual?->id === $programa->id)>{{ $programa->nombre }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="fecha" value="Día del préstamo" />
                <input id="fecha" name="fecha" type="date" value="{{ $filtros['fecha'] ?? '' }}" class="campo-uts mt-1 block w-full text-sm">
            </div>
            <div>
                <x-input-label for="orden" value="Ordenar" />
                <select id="orden" name="orden" class="campo-uts mt-1 block w-full text-sm">
                    @foreach ($ordenes as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected($orden === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <label class="inline-flex cursor-pointer items-center gap-3 text-sm font-semibold text-marca-noche">
                <input type="checkbox" name="choques" value="1" class="peer sr-only" @checked(isset($filtros['choques']))>
                <span class="relative h-6 w-11 shrink-0 rounded-full bg-marca-linea transition after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:bg-marca-bosque peer-checked:after:translate-x-5 peer-focus-visible:ring-4 peer-focus-visible:ring-marca-lima/60"></span>
                Solo las que tienen otra solicitud del mismo bloque
            </label>
            <div class="flex items-center gap-3">
                @if ($etiquetas || $orden !== 'antiguas')
                    <a class="enlace text-sm" href="{{ route('prestamos.pendientes') }}">Limpiar todo</a>
                @endif
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-full bg-marca-oliva px-5 py-2 font-display text-sm font-bold text-white transition-colors hover:bg-marca-bosque active:bg-marca-noche focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/60">
                    <x-icono nombre="buscar" class="h-4 w-4" />Filtrar
                </button>
            </div>
        </div>
    </form>

    {{-- Filtros puestos --}}
    @if ($etiquetas)
        <ul class="mt-4 flex flex-wrap items-center gap-2" aria-label="Filtros puestos">
            @foreach ($etiquetas as $clave => $texto)
                <li>
                    <a href="{{ $url(array_merge([$clave => null], $clave === 'tipo' ? ['categoria' => null] : [])) }}" title="Quitar este filtro"
                       class="inline-flex items-center gap-1.5 rounded-full bg-marca-limaclaro px-3 py-1 text-xs font-bold text-marca-bosque ring-1 ring-inset ring-marca-lima/60 transition hover:bg-marca-lima/40">
                        {{ $texto }}<span aria-hidden="true" class="text-sm leading-none">×</span><span class="sr-only">(quitar)</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    <p class="mb-4 mt-5 text-sm text-marca-suave">
        {{ $pendientes->total() }} {{ $pendientes->total() === 1 ? 'solicitud esperando respuesta' : 'solicitudes esperando respuesta' }}@if ($etiquetas) con estos filtros @endif
        · {{ mb_strtolower($ordenes[$orden]) }}.
    </p>

    @if ($pendientes->isEmpty())
        <div class="rounded-3xl border-2 border-dashed border-marca-linea px-6 py-12 text-center">
            <x-icono nombre="check" class="mx-auto h-8 w-8 text-marca-oliva" />
            @if ($etiquetas)
                <p class="mt-3 font-display font-bold text-marca-noche">No hay solicitudes por aprobar con estos filtros.</p>
                <a href="{{ route('prestamos.pendientes') }}" class="enlace mt-2 inline-block text-sm">Ver todas las solicitudes por aprobar</a>
            @else
                <p class="mt-3 font-display font-bold text-marca-noche">No hay solicitudes por aprobar.</p>
            @endif
        </div>
    @else
        <ul class="space-y-4">
            @foreach ($pendientes as $prestamo)
                @php($otras = $choques[$prestamo->id] ?? collect())
                <li class="rounded-3xl border bg-white p-5 {{ $otras->isNotEmpty() ? 'border-amber-300' : 'border-marca-linea' }}">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-marca-limaclaro text-marca-oliva">
                                <x-icono :nombre="$prestamo->equipo->categoria->iconoVisible()" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <h3 class="font-display text-base font-extrabold text-marca-noche">{{ $prestamo->equipo->nombre }}</h3>
                                <p class="flex flex-wrap items-center gap-x-2 text-sm text-marca-noche">
                                    <x-icono nombre="calendario" class="h-4 w-4 text-marca-oliva" />{{ ucfirst($prestamo->fechaLarga()) }}
                                    <span aria-hidden="true">·</span>
                                    <x-icono nombre="reloj" class="h-4 w-4 text-marca-oliva" />{{ $prestamo->bloque() }}
                                </p>
                            </div>
                        </div>
                        <div class="text-right text-xs text-marca-suave">
                            Solicitada el {{ $cuando($prestamo->created_at) }}
                            <span class="block font-bold text-marca-noche">Espera desde {{ $prestamo->created_at->locale('es')->diffForHumans() }}</span>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                        <x-icono nombre="perfil" class="h-4 w-4 text-marca-oliva" />
                        <b class="text-marca-noche">{{ $prestamo->solicitante->name }}</b>
                        <span class="insignia insignia-{{ $prestamo->solicitante->rol }}">{{ $prestamo->solicitante->rol }}</span>
                        @if ($prestamo->solicitante->programa)
                            <span class="text-marca-suave">{{ $prestamo->solicitante->programa->nombre }} · {{ $prestamo->solicitante->programa->sede }}</span>
                        @endif
                    </div>

                    @if ($otras->isNotEmpty())
                        <p class="mt-3 flex items-start gap-2 rounded-2xl bg-amber-50 px-4 py-2.5 text-sm text-amber-900 ring-1 ring-inset ring-amber-200">
                            <x-icono nombre="alerta" class="mt-0.5 h-4 w-4 shrink-0" />
                            {{ $otras->count() === 1 ? 'Hay otra solicitud' : 'Hay '.$otras->count().' solicitudes más' }} para este mismo bloque. Revísala antes de aprobar.
                        </p>
                    @endif

                    <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-marca-linea pt-4">
                        <a href="{{ route('prestamos.show', array_filter([$prestamo->id, 'volver' => $volver])) }}" class="boton-principal !px-5 !py-2 text-xs">{{ $otras->isNotEmpty() ? 'Revisar y atender' : 'Ver y atender' }}</a>
                        @if ($otras->isEmpty())
                            <form method="POST" action="{{ route('prestamos.aprobar', $prestamo) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="volver" value="{{ $volver }}">
                                <x-secondary-button type="submit" class="!px-4 !py-1.5 text-xs"><x-icono nombre="check" class="h-4 w-4" />Aprobar</x-secondary-button>
                            </form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        @if ($pendientes->hasPages())
            <div class="mt-6">{{ $pendientes->links() }}</div>
        @endif
    @endif
</x-modulo>
