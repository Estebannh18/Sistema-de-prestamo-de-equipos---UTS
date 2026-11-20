@use('App\Reportes\ReportePrestamos')

@php
    // Color de cada estado en indicadores y gráficas.
    $colores = [
        'pendiente' => 'bg-amber-400',
        'aprobado' => 'bg-emerald-500',
        'entregado' => 'bg-sky-500',
        'atrasado' => 'bg-rose-600',
        'devuelto' => 'bg-teal-700',
        'novedad' => 'bg-orange-400',
        'cerrado' => 'bg-slate-300',
    ];
    $total = $indicadores['total'];
    $maximoSerie = max(1, $serie->max('total'));
    $cadaCuanto = max(1, (int) ceil($serie->count() / 12)); // etiquetas del eje: unas 12 como máximo
    $maximoPedidos = max(1, $masPedidos->max('total') ?? 0);
    $maximoCarreras = max(1, $carreras->max('total') ?? 0);
    $aprobados = $indicadores['automatica'] + $indicadores['funcionario'];
    $hayFiltros = $reporte->estado || $reporte->tipo || $reporte->categoria || $reporte->mios;
@endphp

<x-modulo titulo="Reporte de préstamos" :pendiente="false">
    {{-- Encabezado: cuándo se actualizó y exportar --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <p class="flex items-center gap-2 text-sm text-marca-suave" role="status">
            <x-icono nombre="reloj" class="h-4 w-4" />
            Datos al {{ now()->format('d/m/Y') }} a las <span class="tabular-nums">{{ now()->format('h:i:s a') }}</span> · se actualiza cada 30 segundos
        </p>
        <a class="boton-principal text-sm" href="{{ route('reportes.prestamos.pdf', $reporte->parametros()) }}">
            <x-icono nombre="descargar" class="h-4 w-4" />Exportar a PDF
        </a>
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('reportes.prestamos') }}" class="mt-5 grid gap-3 rounded-3xl bg-marca-crema p-4 ring-1 ring-inset ring-marca-linea sm:grid-cols-2 lg:grid-cols-[repeat(5,minmax(0,1fr))_auto]">
        <div>
            <x-input-label for="desde" value="Desde" />
            <x-text-input id="desde" name="desde" type="date" class="mt-1 block w-full" :value="$reporte->desde->toDateString()" />
            <x-input-error :messages="$errors->get('desde')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="hasta" value="Hasta" />
            <x-text-input id="hasta" name="hasta" type="date" class="mt-1 block w-full" :value="$reporte->hasta->toDateString()" />
            <x-input-error :messages="$errors->get('hasta')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="estado" value="Estado" />
            <select id="estado" name="estado" class="mt-1 block w-full campo-uts">
                <option value="">Todos</option>
                @foreach (ReportePrestamos::ESTADOS as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected($reporte->estado === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="tipo" value="Tipo" />
            <select id="tipo" name="tipo" class="mt-1 block w-full campo-uts">
                <option value="">Equipos y espacios</option>
                <option value="equipo" @selected($reporte->tipo === 'equipo')>Equipos</option>
                <option value="espacio" @selected($reporte->tipo === 'espacio')>Espacios</option>
            </select>
        </div>
        <div>
            <x-input-label for="categoria" value="Categoría" />
            <select id="categoria" name="categoria" class="mt-1 block w-full campo-uts">
                <option value="">Todas</option>
                @foreach ($categorias as $opcion)
                    <option value="{{ $opcion->id }}" @selected($reporte->categoria === $opcion->id)>{{ $opcion->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-3">
            <x-secondary-button type="submit"><x-icono nombre="filtro" class="me-1.5 h-4 w-4" />Filtrar</x-secondary-button>
        </div>
        <label class="flex items-center gap-2 text-sm font-semibold text-marca-noche sm:col-span-2 lg:col-span-6">
            <input type="checkbox" name="mios" value="1" class="rounded border-marca-linea text-marca-oliva focus:ring-marca-lima" @checked($reporte->mios)>
            Solo las solicitudes que atendí yo
            @if ($hayFiltros || request()->hasAny(['desde', 'hasta']))
                <a class="enlace ms-auto text-sm" href="{{ route('reportes.prestamos') }}">Limpiar filtros</a>
            @endif
        </label>
    </form>

    {{-- Indicadores: no dependen del estado elegido; cada uno filtra por su estado --}}
    <section class="mt-8" aria-labelledby="titulo-indicadores">
        <h2 id="titulo-indicadores" class="sr-only">Indicadores del periodo</h2>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl bg-marca-bosque px-5 py-4 text-white lg:col-span-1">
                <p class="font-display text-xs font-bold uppercase tracking-wider text-marca-lima">Solicitudes</p>
                <p class="mt-1 font-display text-3xl font-black tabular-nums">{{ $total }}</p>
                <p class="text-xs text-white/70">del {{ $reporte->desde->format('d/m') }} al {{ $reporte->hasta->format('d/m/Y') }}</p>
            </div>
            @foreach (ReportePrestamos::ESTADOS as $valor => $etiqueta)
                @php($activo = $reporte->estado === $valor)
                <a href="{{ route('reportes.prestamos', array_merge($reporte->parametros(), ['estado' => $activo ? null : $valor])) }}" title="{{ ReportePrestamos::AYUDAS[$valor] }}"
                   @if ($activo) aria-current="true" @endif
                   class="group rounded-2xl px-4 py-4 ring-1 ring-inset transition focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/60 {{ $activo ? 'bg-marca-noche text-white ring-marca-noche' : 'bg-white ring-marca-linea hover:ring-marca-oliva' }} {{ $valor === 'atrasado' && $indicadores['estados'][$valor] > 0 && ! $activo ? '!ring-2 !ring-rose-300' : '' }}">
                    <p class="flex items-center gap-1.5 font-display text-xs font-bold uppercase tracking-wider {{ $activo ? 'text-marca-lima' : 'text-marca-suave' }}">
                        <span class="h-2 w-2 rounded-full {{ $colores[$valor] }}" aria-hidden="true"></span>{{ $etiqueta }}
                    </p>
                    <p class="mt-1 font-display text-2xl font-black tabular-nums {{ $valor === 'atrasado' && ! $activo && $indicadores['estados'][$valor] > 0 ? 'text-rose-700' : '' }}">{{ $indicadores['estados'][$valor] }}</p>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Gráficas --}}
    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <section class="rounded-3xl border border-marca-linea bg-white p-5 lg:col-span-2" aria-labelledby="titulo-serie">
            <h2 id="titulo-serie" class="font-display text-base font-extrabold text-marca-noche">Solicitudes por {{ $serie->count() && str_contains($serie->first()['etiqueta'], '/') ? 'día' : 'mes' }}</h2>
            <p class="text-xs text-marca-suave">{{ $reporte->estado ? ReportePrestamos::ESTADOS[$reporte->estado] : 'Todos los estados' }}</p>
            <div class="mt-4 flex h-44 items-end gap-[2px] border-b border-l border-marca-linea pl-1" role="img"
                 aria-label="Solicitudes por periodo: {{ $serie->map(fn ($punto) => $punto['etiqueta'].' '.$punto['total'])->implode(', ') }}">
                @foreach ($serie as $punto)
                    <div class="group relative flex h-full flex-1 items-end" title="{{ $punto['etiqueta'] }}: {{ $punto['total'] }}">
                        <div class="w-full rounded-t-sm bg-marca-oliva transition group-hover:bg-marca-lima" style="height: {{ $punto['total'] * 100 / $maximoSerie }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-1 flex gap-[2px] pl-1 text-[10px] tabular-nums text-marca-suave" aria-hidden="true">
                @foreach ($serie as $punto)
                    <span class="flex-1 overflow-visible whitespace-nowrap">{{ $loop->index % $cadaCuanto === 0 ? $punto['etiqueta'] : '' }}</span>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-marca-suave">Máximo: <span class="font-bold tabular-nums">{{ $serie->max('total') ?? 0 }}</span> solicitudes</p>
        </section>

        <section class="rounded-3xl border border-marca-linea bg-white p-5" aria-labelledby="titulo-distribucion">
            <h2 id="titulo-distribucion" class="font-display text-base font-extrabold text-marca-noche">Distribución por estado</h2>
            @if ($total > 0)
                <div class="mt-4 flex h-3 overflow-hidden rounded-full bg-marca-crema" role="img"
                     aria-label="{{ collect(ReportePrestamos::ESTADOS)->map(fn ($etiqueta, $valor) => $etiqueta.' '.$indicadores['estados'][$valor])->implode(', ') }}">
                    @foreach ($indicadores['estados'] as $valor => $cantidad)
                        @if ($cantidad > 0)
                            <span class="{{ $colores[$valor] }}" style="width: {{ $cantidad * 100 / $total }}%"></span>
                        @endif
                    @endforeach
                </div>
                <ul class="mt-4 space-y-1.5 text-sm">
                    @foreach (ReportePrestamos::ESTADOS as $valor => $etiqueta)
                        <li class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full {{ $colores[$valor] }}" aria-hidden="true"></span>{{ $etiqueta }}</span>
                            <span class="tabular-nums text-marca-suave"><span class="font-bold text-marca-noche">{{ $indicadores['estados'][$valor] }}</span> · {{ round($indicadores['estados'][$valor] * 100 / $total) }} %</span>
                        </li>
                    @endforeach
                </ul>
                @if ($aprobados > 0)
                    <div class="mt-4 border-t border-marca-linea pt-3 text-xs text-marca-suave">
                        <p class="font-bold text-marca-noche">Cómo se aprobaron</p>
                        <p class="mt-1 tabular-nums">{{ $indicadores['automatica'] }} automáticas · {{ $indicadores['funcionario'] }} por un funcionario</p>
                    </div>
                @endif
            @else
                <p class="mt-4 text-sm text-marca-suave">No hay solicitudes en este periodo.</p>
            @endif
        </section>
    </div>

    {{-- Rankings --}}
    <div class="mt-4 grid gap-4 md:grid-cols-2">
        @foreach ([['Lo más pedido', 'equipos', $masPedidos, $maximoPedidos], ['Carreras que más solicitan', 'graduado', $carreras, $maximoCarreras]] as [$titulo, $icono, $filas, $maximo])
            <section class="rounded-3xl border border-marca-linea bg-white p-5" aria-label="{{ $titulo }}">
                <h2 class="flex items-center gap-2 font-display text-base font-extrabold text-marca-noche">
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-marca-limaclaro text-marca-bosque"><x-icono :nombre="$icono" class="h-4 w-4" /></span>
                    {{ $titulo }}
                </h2>
                @if ($filas->isEmpty())
                    <p class="mt-4 text-sm text-marca-suave">Sin datos en este periodo.</p>
                @else
                    <ol class="mt-4 space-y-3">
                        @foreach ($filas as $fila)
                            <li>
                                <div class="flex items-baseline justify-between gap-3 text-sm">
                                    <span class="min-w-0 truncate"><span class="me-1.5 font-bold tabular-nums text-marca-suave">{{ $loop->iteration }}.</span>{{ $fila->nombre }}@isset($fila->codigo) <span class="font-mono text-xs text-marca-suave">{{ $fila->codigo }}</span>@endisset</span>
                                    <span class="shrink-0 font-bold tabular-nums">{{ $fila->total }}</span>
                                </div>
                                <div class="mt-1 h-1.5 rounded-full bg-marca-crema"><div class="h-full rounded-full bg-marca-oliva" style="width: {{ $fila->total * 100 / $maximo }}%"></div></div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        @endforeach
    </div>

    {{-- Detalle --}}
    <section class="mt-8" aria-labelledby="titulo-detalle">
        <div class="flex flex-wrap items-baseline justify-between gap-3">
            <h2 id="titulo-detalle" class="flex items-center gap-3 font-display text-xl font-extrabold text-marca-noche">
                <span class="h-6 w-1.5 rounded-full bg-marca-lima" aria-hidden="true"></span>
                Detalle
            </h2>
            <p class="text-sm tabular-nums text-marca-suave">{{ $prestamos->total() }} {{ $prestamos->total() === 1 ? 'solicitud' : 'solicitudes' }}</p>
        </div>

        @if ($prestamos->isEmpty())
            <div class="mt-4 rounded-3xl border-2 border-dashed border-marca-linea px-6 py-10 text-center">
                <x-icono nombre="reporte" class="mx-auto h-8 w-8 text-marca-gris" />
                <p class="mt-3 font-display font-bold text-marca-noche">No hay solicitudes con estos filtros.</p>
            </div>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Bloque</th>
                            <th>Equipo o espacio</th>
                            <th>Solicitante</th>
                            <th>Estado</th>
                            <th>Atendió</th>
                            <th><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($prestamos as $prestamo)
                            <tr>
                                <td class="whitespace-nowrap tabular-nums">{{ $prestamo->fecha?->format('d/m/Y') }}</td>
                                <td class="whitespace-nowrap tabular-nums">{{ $prestamo->bloque() }}</td>
                                <td>
                                    <p class="font-semibold">{{ $prestamo->equipo->nombre }}</p>
                                    <p class="text-xs text-marca-suave"><span class="font-mono">{{ $prestamo->equipo->codigo_inventario }}</span> · {{ $prestamo->equipo->categoria->nombre }}</p>
                                </td>
                                <td>
                                    <p class="font-semibold">{{ $prestamo->solicitante->name }}</p>
                                    <p class="text-xs capitalize text-marca-suave">{{ $prestamo->solicitante->rol }}@if ($prestamo->solicitante->programa) · <span class="normal-case">{{ $prestamo->solicitante->programa->nombre }}</span>@endif</p>
                                </td>
                                <td><span class="insignia {{ ReportePrestamos::insigniaDe($prestamo) }} !normal-case">{{ ReportePrestamos::nombreEstadoDe($prestamo) }}</span></td>
                                <td class="text-sm">{{ $prestamo->funcionario?->name ?? ($prestamo->aprobacion === 'automatica' ? 'Automática' : '—') }}</td>
                                <td><a class="enlace text-sm" href="{{ route('prestamos.show', $prestamo) }}">Ver</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $prestamos->links() }}</div>
        @endif
    </section>

    {{-- RNF-10: la vista abierta se refresca sola cada 30 segundos y conserva los filtros (están en la URL).
         Si la pestaña no está a la vista, espera a que vuelva a estarlo. --}}
    <script>
        (() => {
            const refrescar = () => document.hidden
                ? document.addEventListener('visibilitychange', refrescar, { once: true })
                : window.location.reload();
            setTimeout(refrescar, 30000);
        })();
    </script>
</x-modulo>
