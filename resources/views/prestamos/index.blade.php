{{--
    Estudiantes y docentes: módulo «Solicitudes». Lista sus solicitudes, con su estado y la opción de cancelar,
    y el botón «Crear solicitud», que abre el formulario por pasos (prestamos.create).
    Personal: todos los préstamos, con filtro por estado.
--}}
@php
    use App\Models\Configuracion;

    $estados = [
        null => 'Todas', 'pendiente' => 'Pendientes', 'aprobado' => 'Aprobadas', 'entregado' => 'Entregadas', 'por_corregir' => 'Por corregir',
        'devuelto' => 'Devueltas', 'rechazado' => 'Rechazadas', 'cancelado' => 'Canceladas', 'no_recogido' => 'No recogidas',
        'no_entregado' => 'No entregadas', 'devolucion_rechazada' => 'Devolución rechazada',
    ];
    $puedeSolicitar = Auth::user()->can('solicitar', App\Models\Prestamo::class);
    $cuando = fn ($fecha) => $fecha?->format('d/m/Y \a \l\a\s g:i a');
    $hora = fn ($fecha) => $fecha?->locale('es')->isoFormat('h:mm a');
@endphp

<x-modulo :titulo="$esPersonal ? 'Préstamos' : 'Solicitudes'" :pendiente="false">
    @if (session('status'))
        <p class="aviso-ok"><x-icono nombre="check" class="h-5 w-5 shrink-0" />{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <p class="aviso-error"><x-icono nombre="alerta" class="h-5 w-5 shrink-0" />{{ $errors->first() }}</p>
    @endif

    @unless ($esPersonal)
        {{-- Encabezado: mis solicitudes y el botón para crear una nueva --}}
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-3xl bg-marca-crema/70 p-5 ring-1 ring-inset ring-marca-linea">
            <div class="flex items-center gap-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-marca-lima text-marca-noche shadow-[0_5px_0_0_#7b8305]">
                    <x-icono nombre="solicitud" class="h-6 w-6" />
                </span>
                <div>
                    <h2 class="font-display text-xl font-extrabold text-marca-noche">Mis solicitudes</h2>
                    <p class="text-sm text-marca-suave">
                        Pide un equipo o un espacio en 5 pasos: el día, qué necesitas, la categoría, cuál y el bloque.
                    </p>
                </div>
            </div>
            @if ($puedeSolicitar)
                <a class="boton-principal" href="{{ route('prestamos.create') }}"><x-icono nombre="mas" class="h-5 w-5" />Crear solicitud</a>
            @endif
        </div>
    @endunless

    <div class="flex flex-wrap items-center justify-between gap-4">
        <nav class="flex flex-wrap gap-2" aria-label="Filtrar por estado">
            @foreach ($estados as $valor => $etiqueta)
                @php $activa = $estadoActual === ($valor ?: null); @endphp
                <a href="{{ route('prestamos.index', array_filter(['estado' => $valor ?: null])) }}" @if ($activa) aria-current="page" @endif
                   class="rounded-full px-4 py-1.5 font-display text-sm font-bold ring-1 ring-inset transition focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/60 {{ $activa ? 'bg-marca-bosque text-white ring-marca-bosque' : 'bg-white text-marca-bosque ring-marca-linea hover:bg-marca-crema' }}">
                    {{ $etiqueta }}
                </a>
            @endforeach
        </nav>
        @if ($esPersonal)
            <a class="boton-principal text-sm" href="{{ route('prestamos.pendientes') }}"><x-icono nombre="reloj" class="h-4 w-4" />Solicitudes por aprobar</a>
        @endif
    </div>

    @if ($prestamos->isEmpty())
        <div class="mt-6 rounded-3xl border-2 border-dashed border-marca-linea px-6 py-12 text-center">
            <x-icono nombre="prestamos" class="mx-auto h-8 w-8 text-marca-gris" />
            <p class="mt-3 font-display font-bold text-marca-noche">{{ $esPersonal ? 'No hay préstamos para mostrar.' : ($estadoActual ? 'No tienes solicitudes con ese estado.' : 'Todavía no tienes solicitudes.') }}</p>
            @if (! $esPersonal && $puedeSolicitar && ! $estadoActual)
                <a class="boton-principal mt-5 text-sm" href="{{ route('prestamos.create') }}"><x-icono nombre="mas" class="h-4 w-4" />Crear mi primera solicitud</a>
            @endif
        </div>
    @elseif ($esPersonal)
        <div class="mt-6 overflow-x-auto">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Solicitante</th>
                        <th>Qué</th>
                        <th>Día y bloque</th>
                        <th>Estado</th>
                        <th><span class="sr-only">Detalle</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($prestamos as $prestamo)
                        <tr>
                            <td>
                                {{ $prestamo->solicitante->name }}
                                <span class="block text-xs text-marca-suave">{{ $prestamo->solicitante->programa?->nombre ?? ucfirst($prestamo->solicitante->rol) }}</span>
                            </td>
                            <td>{{ $prestamo->equipo->nombre }}<span class="block text-xs text-marca-suave">{{ $prestamo->equipo->categoria->nombre }}</span></td>
                            <td class="whitespace-nowrap">{{ $prestamo->fecha?->format('d/m/Y') ?? '—' }}<span class="block text-xs text-marca-suave">{{ $prestamo->bloque() }}</span></td>
                            <td><span class="insignia insignia-{{ $prestamo->estado }} normal-case">{{ $prestamo->nombreEstado() }}</span></td>
                            <td><a class="enlace text-sm" href="{{ route('prestamos.show', $prestamo) }}">Ver</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <ul class="mt-6 space-y-4">
            @foreach ($prestamos as $prestamo)
                <li id="solicitud-{{ $prestamo->id }}" class="scroll-mt-28 rounded-3xl border border-marca-linea bg-white p-5 target:ring-4 target:ring-marca-lima/60">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-marca-limaclaro text-marca-oliva">
                                <x-icono :nombre="$prestamo->equipo->categoria->iconoVisible()" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <h3 class="font-display text-base font-extrabold text-marca-noche">{{ $prestamo->equipo->nombre }}</h3>
                                <p class="flex flex-wrap items-center gap-x-2 text-sm text-marca-suave">
                                    <x-icono nombre="calendario" class="h-4 w-4" />{{ ucfirst($prestamo->fechaLarga()) }}
                                    <span aria-hidden="true">·</span>
                                    <x-icono nombre="reloj" class="h-4 w-4" />{{ $prestamo->bloque() }}
                                </p>
                            </div>
                        </div>
                        <span class="insignia insignia-{{ $prestamo->estado }} normal-case">{{ $prestamo->nombreEstado() }}</span>
                    </div>

                    <div class="mt-3 text-sm">
                        @php
                            $ultima = $prestamo->ultimaDevolucion;
                            $aviso = 'rounded-2xl px-4 py-2.5 ring-1 ring-inset';
                        @endphp
                        @if ($prestamo->estado === 'aprobado')
                            <p class="text-emerald-800">{{ $prestamo->aprobacion === 'automatica' ? 'Aprobada automáticamente: estaba libre.' : 'Aprobada por '.($prestamo->funcionario?->name ?? 'el funcionario').'.' }}
                                Recógelo en la dependencia de préstamos desde las {{ $hora($prestamo->inicioEntrega()) }}; solo tú lo puedes recoger.</p>
                        @elseif ($prestamo->estado === 'pendiente')
                            <p class="text-amber-800">Esperando la respuesta del funcionario. Te avisaremos en la campana.</p>
                        @elseif ($prestamo->estado === 'rechazado')
                            <p class="{{ $aviso }} bg-rose-50 text-rose-800 ring-rose-200"><b>Motivo:</b> {{ $prestamo->motivo_rechazo }}</p>
                        @elseif ($prestamo->estado === 'cancelado')
                            @if ($prestamo->cancelada_por === 'solicitante')
                                <p class="text-marca-suave">La cancelaste el {{ $cuando($prestamo->cancelado_en) }}.</p>
                            @else
                                <p class="{{ $aviso }} bg-rose-50 text-rose-800 ring-rose-200">
                                    {{ $prestamo->cancelada_por === 'sistema' ? 'Se canceló porque ya no está disponible.' : 'La canceló la dependencia de préstamos.' }}
                                    <b>Motivo:</b> {{ $prestamo->motivo_cancelacion }}
                                </p>
                            @endif
                        @elseif ($prestamo->estado === 'entregado')
                            <p class="{{ $prestamo->estaAtrasado() ? $aviso.' bg-rose-50 text-rose-800 ring-rose-200' : 'text-sky-800' }}">
                                Lo recibiste el {{ $cuando($prestamo->entrega?->entregado_en) }}. Devuélvelo hasta las {{ $hora($prestamo->limiteDevolucion()) }}@if ($prestamo->estaAtrasado()): <b>ya pasó la hora límite</b>@endif.
                            </p>
                        @elseif ($prestamo->estado === 'no_recogido')
                            <p class="text-marca-suave">El bloque terminó y no se recogió.</p>
                        @elseif ($prestamo->estado === 'no_entregado')
                            <p class="{{ $aviso }} bg-rose-50 text-rose-800 ring-rose-200">No se pudo entregar. <b>Motivo:</b> {{ $prestamo->motivo_no_entrega }}</p>
                        @elseif ($prestamo->estado === 'por_corregir' && $ultima)
                            <p class="{{ $aviso }} bg-amber-50 text-amber-900 ring-amber-200">
                                La devolución no se recibió todavía. <b>Motivo:</b> {{ $ultima->motivo }}@if ($ultima->plazo) · <b>Plazo:</b> {{ $ultima->plazo }}@endif
                            </p>
                        @elseif ($prestamo->estado === 'devuelto' && $ultima)
                            <p class="text-marca-suave">Lo devolviste el {{ $cuando($ultima->recibido_en) }}@if ($ultima->conRetraso()), con {{ Configuracion::duracion($ultima->minutos_retraso) }} de retraso @endif.</p>
                            @if ($ultima->tienePendienteSinResolver())
                                <p class="mt-2 {{ $aviso }} bg-amber-50 text-amber-900 ring-amber-200"><b>Queda un pendiente:</b> {{ $ultima->pendiente }}</p>
                            @endif
                        @elseif ($prestamo->estado === 'devolucion_rechazada' && $ultima)
                            <p class="{{ $aviso }} bg-rose-50 text-rose-800 ring-rose-200">La devolución fue rechazada y quedó pendiente de gestión. <b>Motivo:</b> {{ $ultima->motivo }}</p>
                        @endif
                    </div>

                    @if (in_array($prestamo->estado, App\Models\Prestamo::CANCELABLES, true))
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-marca-linea pt-4">
                            @if ($prestamo->sePuedeCancelar())
                                <span class="text-xs text-marca-suave">Puedes cancelarla hasta el {{ $prestamo->limiteCancelacion()->format('d/m/Y \a \l\a\s g:i a') }}.</span>
                                <form method="POST" action="{{ route('prestamos.cancelar', $prestamo) }}" onsubmit="return confirm('¿Cancelar tu solicitud de {{ e($prestamo->equipo->nombre) }} del {{ $prestamo->fechaLarga() }}?');">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-full border-2 border-rose-300 bg-white px-4 py-1.5 font-display text-xs font-bold text-rose-700 transition hover:border-rose-700 hover:bg-rose-700 hover:text-white focus:outline-none focus-visible:ring-4 focus-visible:ring-rose-200">
                                        <x-icono nombre="cerrar" class="h-4 w-4" />Cancelar solicitud
                                    </button>
                                </form>
                            @else
                                <span class="text-xs text-marca-suave">Ya no se puede cancelar: el límite era {{ Configuracion::duracion(Configuracion::minutosCancelacion(Auth::user())) }} antes del inicio.</span>
                            @endif
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($prestamos->hasPages())
        <div class="mt-6">{{ $prestamos->links() }}</div>
    @endif
</x-modulo>
