{{--
    Módulo Devoluciones (funcionario y administrador). Pestañas: Por recibir (las atrasadas resaltadas),
    Por corregir, Con pendiente e Historial.
--}}
@php
    use App\Http\Controllers\DevolucionController;

    $url = fn (array $cambios = []) => route('devoluciones.index', array_filter(array_merge(['ver' => $pestana, 'buscar' => $buscar], $cambios), fn ($v) => $v !== null && $v !== '' && $v !== 'por_recibir'));
    $hora = fn ($fecha) => $fecha?->locale('es')->isoFormat('h:mm a');
@endphp

<x-modulo titulo="Devoluciones" :pendiente="false">
    @if (session('status'))
        <p class="aviso-ok"><x-icono nombre="check" class="h-5 w-5 shrink-0" />{{ session('status') }}</p>
    @endif
    @if ($errors->has('solicitud'))
        <p class="aviso-error"><x-icono nombre="alerta" class="h-5 w-5 shrink-0" />{{ $errors->first('solicitud') }}</p>
    @endif

    <p class="text-sm text-marca-suave">
        Al recibir, compara con lo que se anotó en la entrega y elige el resultado: aceptada (con o sin pendiente), por corregir o rechazada.
        Pasada la hora límite, la devolución queda con retraso.
    </p>

    <div class="mt-5 flex flex-wrap items-end justify-between gap-4">
        <nav class="flex flex-wrap gap-1 rounded-3xl bg-marca-crema p-1 ring-1 ring-inset ring-marca-linea sm:rounded-full" aria-label="Pestañas">
            @foreach (DevolucionController::PESTANAS as $clave => $etiqueta)
                @php $activa = $pestana === $clave; @endphp
                <a href="{{ $url(['ver' => $clave, 'page' => null]) }}" @if ($activa) aria-current="page" @endif
                   class="inline-flex items-center gap-2 rounded-full px-4 py-2 font-display text-sm font-bold transition focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/60 {{ $activa ? 'bg-marca-bosque text-white shadow' : 'text-marca-bosque hover:bg-white' }}">
                    {{ $etiqueta }}
                    <span class="rounded-full px-2 py-0.5 text-xs {{ $activa ? 'bg-white/20' : 'bg-white text-marca-noche' }}">{{ $conteos[$clave] }}</span>
                </a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('devoluciones.index') }}" class="relative w-full sm:w-80">
            @if ($pestana !== 'por_recibir')<input type="hidden" name="ver" value="{{ $pestana }}">@endif
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
            <x-icono nombre="devolucion" class="mx-auto h-8 w-8 text-marca-oliva" />
            <p class="mt-3 font-display font-bold text-marca-noche">
                {{ match ($pestana) {
                    'por_recibir' => 'No hay préstamos por recibir.',
                    'por_corregir' => 'No hay devoluciones por corregir.',
                    'con_pendiente' => 'No hay devoluciones con pendientes.',
                    default => 'Todavía no se ha registrado ninguna devolución.',
                } }}
            </p>
        </div>
    @else
        <ul class="mt-5 space-y-3">
            @foreach ($prestamos as $prestamo)
                @php
                    $atrasado = $prestamo->estaAtrasado();
                    $ultima = $prestamo->ultimaDevolucion;
                @endphp
                <li class="rounded-3xl border bg-white p-5 {{ $atrasado ? 'border-rose-300' : ($prestamo->estado === 'por_corregir' ? 'border-amber-300' : 'border-marca-linea') }}">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-marca-limaclaro text-marca-oliva">
                                <x-icono :nombre="$prestamo->equipo->categoria->iconoVisible()" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <h3 class="font-display text-base font-extrabold text-marca-noche">{{ $prestamo->equipo->nombre }} <span class="text-xs font-semibold text-marca-suave">· {{ $prestamo->equipo->codigo_inventario }}</span></h3>
                                <p class="text-sm text-marca-noche">{{ ucfirst($prestamo->fechaLarga()) }} · {{ $prestamo->bloque() }}</p>
                                <p class="mt-1 flex flex-wrap items-center gap-x-2 text-sm">
                                    <x-icono nombre="perfil" class="h-4 w-4 text-marca-oliva" /><b class="text-marca-noche">{{ $prestamo->solicitante->name }}</b>
                                    <span class="insignia insignia-{{ $prestamo->solicitante->rol }}">{{ $prestamo->solicitante->rol }}</span>
                                    <a class="enlace text-xs" href="mailto:{{ $prestamo->solicitante->email }}">{{ $prestamo->solicitante->email }}</a>
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-1">
                            <span class="insignia insignia-{{ $prestamo->estado }} normal-case">{{ $prestamo->nombreEstado() }}</span>
                            @if ($atrasado)
                                <span class="text-xs font-bold text-rose-700">Atrasado {{ App\Models\Configuracion::duracion($prestamo->minutosDeRetraso()) }}</span>
                            @endif
                        </div>
                    </div>

                    <p class="mt-3 text-sm text-marca-suave">
                        @if ($pestana === 'por_recibir')
                            Entregado a las {{ $hora($prestamo->entrega?->entregado_en) }} · devolver hasta las {{ $hora($prestamo->limiteDevolucion()) }}
                        @elseif ($ultima)
                            {{ $ultima->resultadoLegible() }} el {{ $ultima->recibido_en->format('d/m/Y \a \l\a\s g:i a') }} por {{ $ultima->funcionario?->name ?? '—' }}
                            @if ($ultima->conRetraso()) · con {{ App\Models\Configuracion::duracion($ultima->minutos_retraso) }} de retraso @endif.
                        @endif
                    </p>
                    @if ($prestamo->estado === 'por_corregir' && $ultima)
                        <p class="mt-1 text-sm text-amber-900"><b>Motivo:</b> {{ $ultima->motivo }}@if ($ultima->plazo) · <b>Plazo:</b> {{ $ultima->plazo }}@endif</p>
                    @endif
                    @if ($ultima?->tienePendienteSinResolver())
                        <p class="mt-1 text-sm text-amber-900"><b>Pendiente:</b> {{ $ultima->pendiente }}</p>
                    @endif

                    <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-marca-linea pt-4">
                        @if (in_array($prestamo->estado, ['entregado', 'por_corregir'], true))
                            <a href="{{ route('devoluciones.create', $prestamo) }}" class="boton-principal !px-5 !py-2 text-xs"><x-icono nombre="devolucion" class="h-4 w-4" />Registrar devolución</a>
                        @endif
                        @if ($ultima?->tienePendienteSinResolver())
                            <form method="POST" action="{{ route('devoluciones.resolver', $ultima) }}">
                                @csrf
                                @method('PATCH')
                                <x-secondary-button type="submit" class="!px-4 !py-1.5 text-xs"><x-icono nombre="check" class="h-4 w-4" />Marcar como resuelto</x-secondary-button>
                            </form>
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
