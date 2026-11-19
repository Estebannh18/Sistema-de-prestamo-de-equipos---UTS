{{--
    Solicitudes por aprobar (funcionario y administrador), de la más antigua a la más reciente.
--}}
@php
    $pestanas = [null => 'Todas', 'espacio' => 'Espacios', 'equipo' => 'Equipos'];
    $cuando = fn ($fecha) => $fecha->locale('es')->isoFormat('ddd D/MM/YYYY, h:mm a');
@endphp

<x-modulo titulo="Solicitudes por aprobar" :pendiente="false">
    @if (session('status'))
        <p class="aviso-ok"><x-icono nombre="check" class="h-5 w-5 shrink-0" />{{ session('status') }}</p>
    @endif
    @if ($errors->has('solicitud'))
        <p class="aviso-error"><x-icono nombre="alerta" class="h-5 w-5 shrink-0" />{{ $errors->first('solicitud') }}</p>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-4">
        <nav class="inline-flex rounded-full bg-marca-crema p-1 ring-1 ring-inset ring-marca-linea" aria-label="Tipo">
            @foreach ($pestanas as $valor => $etiqueta)
                @php($activa = $tipoActual === ($valor ?: null))
                <a href="{{ route('prestamos.pendientes', array_filter(['tipo' => $valor ?: null, 'fecha' => $fechaActual])) }}" @if ($activa) aria-current="page" @endif
                   class="rounded-full px-5 py-2 font-display text-sm font-bold transition focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/60 {{ $activa ? 'bg-marca-bosque text-white shadow' : 'text-marca-bosque hover:bg-white' }}">
                    {{ $etiqueta }}
                </a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('prestamos.pendientes') }}" class="flex flex-wrap items-end gap-3">
            @if ($tipoActual)<input type="hidden" name="tipo" value="{{ $tipoActual }}">@endif
            <div>
                <x-input-label for="fecha" value="Día del préstamo" />
                <x-text-input id="fecha" name="fecha" type="date" class="mt-1 block" :value="$fechaActual" />
            </div>
            <x-secondary-button type="submit">Filtrar</x-secondary-button>
            @if ($fechaActual)
                <a class="enlace text-sm" href="{{ route('prestamos.pendientes', array_filter(['tipo' => $tipoActual])) }}">Quitar fecha</a>
            @endif
        </form>
    </div>

    <p class="mb-4 mt-5 text-sm text-marca-suave">
        {{ $pendientes->total() }} {{ $pendientes->total() === 1 ? 'solicitud esperando respuesta' : 'solicitudes esperando respuesta' }}, de la más antigua a la más reciente.
    </p>

    @if ($pendientes->isEmpty())
        <div class="rounded-3xl border-2 border-dashed border-marca-linea px-6 py-12 text-center">
            <x-icono nombre="check" class="mx-auto h-8 w-8 text-marca-oliva" />
            <p class="mt-3 font-display font-bold text-marca-noche">No hay solicitudes por aprobar.</p>
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
                        <a href="{{ route('prestamos.show', $prestamo) }}" class="boton-principal !px-5 !py-2 text-xs">{{ $otras->isNotEmpty() ? 'Revisar y atender' : 'Ver y atender' }}</a>
                        @if ($otras->isEmpty())
                            <form method="POST" action="{{ route('prestamos.aprobar', $prestamo) }}">
                                @csrf
                                @method('PATCH')
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
