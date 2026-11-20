{{--
    Módulo Entregas (funcionario y administrador). Pestañas: Hoy (por hora), Próximas, No recogidos,
    No entregados y Entregadas. Desde «Hoy» se abre el formulario de entrega.
--}}
@php
    use App\Http\Controllers\EntregaController;

    $url = fn (array $cambios = []) => route('entregas.index', array_filter(array_merge(['ver' => $pestana, 'buscar' => $buscar], $cambios), fn ($v) => $v !== null && $v !== '' && $v !== 'hoy'));
    $hora = fn ($fecha) => $fecha?->locale('es')->isoFormat('h:mm a');
@endphp

<x-modulo titulo="Entregas" :pendiente="false">
    @if (session('status'))
        <p class="aviso-ok"><x-icono nombre="check" class="h-5 w-5 shrink-0" />{{ session('status') }}</p>
    @endif

    <p class="text-sm text-marca-suave">
        Una solicitud aprobada es una reserva: el equipo o espacio se presta cuando registras la entrega, siempre al solicitante en persona.
        Si el bloque termina sin entrega, queda como «No recogido».
    </p>

    <div class="mt-5 flex flex-wrap items-end justify-between gap-4">
        <nav class="flex flex-wrap gap-1 rounded-3xl bg-marca-crema p-1 ring-1 ring-inset ring-marca-linea sm:rounded-full" aria-label="Pestañas">
            @foreach (EntregaController::PESTANAS as $clave => $etiqueta)
                @php $activa = $pestana === $clave; @endphp
                <a href="{{ $url(['ver' => $clave, 'page' => null]) }}" @if ($activa) aria-current="page" @endif
                   class="inline-flex items-center gap-2 rounded-full px-4 py-2 font-display text-sm font-bold transition focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/60 {{ $activa ? 'bg-marca-bosque text-white shadow' : 'text-marca-bosque hover:bg-white' }}">
                    {{ $etiqueta }}
                    <span class="rounded-full px-2 py-0.5 text-xs {{ $activa ? 'bg-white/20' : 'bg-white text-marca-noche' }}">{{ $conteos[$clave] }}</span>
                </a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('entregas.index') }}" class="relative w-full sm:w-80">
            @if ($pestana !== 'hoy')<input type="hidden" name="ver" value="{{ $pestana }}">@endif
            <label for="buscar" class="sr-only">Buscar</label>
            <x-icono nombre="buscar" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-marca-suave" />
            <input id="buscar" name="buscar" type="search" value="{{ $buscar }}" maxlength="80" placeholder="Solicitante, documento, equipo o código"
                   class="campo-uts block w-full pl-10 text-sm placeholder:text-marca-gris">
        </form>
    </div>

    @if ($buscar !== '')
        <p class="mt-3 text-sm text-marca-suave">Resultados para «{{ $buscar }}». <a class="enlace" href="{{ $url(['buscar' => null, 'page' => null]) }}">Quitar la búsqueda</a></p>
    @endif

    @if ($prestamos->isEmpty())
        <div class="mt-6 rounded-3xl border-2 border-dashed border-marca-linea px-6 py-12 text-center">
            <x-icono nombre="llave" class="mx-auto h-8 w-8 text-marca-oliva" />
            <p class="mt-3 font-display font-bold text-marca-noche">
                {{ match ($pestana) {
                    'hoy' => 'No hay préstamos por entregar hoy.',
                    'proximas' => 'No hay préstamos aprobados para los próximos días.',
                    'no_recogidos' => 'No hay préstamos sin recoger.',
                    'no_entregados' => 'No hay préstamos marcados como no entregados.',
                    default => 'Todavía no se ha registrado ninguna entrega.',
                } }}
            </p>
        </div>
    @else
        <ul class="mt-5 space-y-3">
            @foreach ($prestamos as $prestamo)
                @php
                    $anterior = $sinDevolver[$prestamo->id] ?? null;
                    $enHorario = $prestamo->enHorarioDeEntrega();
                @endphp
                <li class="rounded-3xl border bg-white p-5 {{ $anterior ? 'border-rose-300' : ($enHorario ? 'border-marca-lima' : 'border-marca-linea') }}">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-marca-limaclaro text-marca-oliva">
                                <x-icono :nombre="$prestamo->equipo->categoria->iconoVisible()" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <h3 class="font-display text-base font-extrabold text-marca-noche">{{ $prestamo->equipo->nombre }} <span class="text-xs font-semibold text-marca-suave">· {{ $prestamo->equipo->codigo_inventario }}</span></h3>
                                <p class="flex flex-wrap items-center gap-x-2 text-sm text-marca-noche">
                                    <x-icono nombre="calendario" class="h-4 w-4 text-marca-oliva" />{{ ucfirst($prestamo->fechaLarga()) }}
                                    <span aria-hidden="true">·</span>
                                    <x-icono nombre="reloj" class="h-4 w-4 text-marca-oliva" />{{ $prestamo->bloque() }}
                                </p>
                                <p class="mt-1 flex flex-wrap items-center gap-x-2 text-sm">
                                    <x-icono nombre="perfil" class="h-4 w-4 text-marca-oliva" /><b class="text-marca-noche">{{ $prestamo->solicitante->name }}</b>
                                    <span class="insignia insignia-{{ $prestamo->solicitante->rol }}">{{ $prestamo->solicitante->rol }}</span>
                                    <span class="text-marca-suave">{{ $prestamo->solicitante->tipo_documento }} {{ $prestamo->solicitante->documento }}</span>
                                </p>
                            </div>
                        </div>
                        <span class="insignia insignia-{{ $prestamo->estado }} normal-case">{{ $prestamo->nombreEstado() }}</span>
                    </div>

                    @if ($anterior)
                        <p class="mt-3 flex items-start gap-2 rounded-2xl bg-rose-50 px-4 py-2.5 text-sm text-rose-900 ring-1 ring-inset ring-rose-200">
                            <x-icono nombre="alerta" class="mt-0.5 h-4 w-4 shrink-0" />
                            <span>Todavía no se ha devuelto: lo tiene <b>{{ $anterior->solicitante->name }}</b> (préstamo de {{ $anterior->bloque() }}{{ $anterior->estado === 'por_corregir' ? ', por corregir' : '' }}).</span>
                        </p>
                    @endif

                    @if ($pestana === 'no_recogidos')
                            <p class="mt-3 text-sm text-marca-suave">El bloque terminó y nadie lo recogió. No hay devolución.</p>
                    @elseif ($pestana === 'no_entregados')
                            <p class="mt-3 text-sm text-marca-noche"><b>Motivo:</b> {{ $prestamo->motivo_no_entrega }}</p>
                            <p class="text-xs text-marca-suave">Lo marcó {{ $prestamo->noEntregadoPor?->name ?? '—' }} el {{ $prestamo->no_entregado_en?->format('d/m/Y \a \l\a\s g:i a') }}.</p>
                    @elseif ($pestana === 'historial')
                            <p class="mt-3 text-sm text-marca-suave">
                                Entregado el {{ $prestamo->entrega->entregado_en->format('d/m/Y \a \l\a\s g:i a') }}
                                por {{ $prestamo->entrega->funcionario?->name ?? '—' }} · Estado: {{ $prestamo->entrega->estadoLegible() }}.
                            </p>
                    @endif

                    <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-marca-linea pt-4">
                        @if (in_array($pestana, ['hoy', 'proximas'], true))
                            @if ($enHorario && ! $anterior)
                                <a href="{{ route('entregas.create', $prestamo) }}" class="boton-principal !px-5 !py-2 text-xs"><x-icono nombre="llave" class="h-4 w-4" />Registrar entrega</a>
                            @elseif ($enHorario)
                                <a href="{{ route('entregas.create', $prestamo) }}" class="boton-claro !px-5 !py-2 text-xs">Ver y decidir</a>
                            @else
                                <span class="text-xs text-marca-suave">Se entrega desde las {{ $hora($prestamo->inicioEntrega()) }}{{ $prestamo->fecha->isToday() ? '' : ' del '.$prestamo->fecha->format('d/m/Y').'.' }}</span>
                            @endif
                        @endif
                        <a href="{{ route('prestamos.show', $prestamo) }}" class="enlace text-xs">Ver la solicitud</a>
                    </div>
                </li>
            @endforeach
        </ul>

        @if ($prestamos->hasPages())
            <div class="mt-6">{{ $prestamos->links() }}</div>
        @endif
    @endif
</x-modulo>
