{{--
    Detalle de una solicitud. Al funcionario o al administrador, si está pendiente, le muestra las demás
    solicitudes del mismo espacio o equipo y bloque (quién y cuándo), y los botones para aprobar o rechazar.
--}}
@php
    $equipo = $prestamo->equipo;
    $solicitante = $prestamo->solicitante;
    $cuando = fn ($fecha) => $fecha?->locale('es')->isoFormat('dddd D/MM/YYYY [a las] h:mm a');
    $nombresEstado = ['pendiente' => 'pendiente de aprobación', 'aprobado' => 'aprobada', 'rechazado' => 'rechazada', 'cancelado' => 'cancelada', 'devuelto' => 'devuelta'];
    $puedeAtender = $esPersonal && $prestamo->estado === 'pendiente';
    $pendientesQueSeCruzan = $choques->where('estado', 'pendiente');
@endphp

<x-modulo titulo="Solicitud" :pendiente="false">
    @if (session('status'))
        <p class="aviso-ok"><x-icono nombre="check" class="h-5 w-5 shrink-0" />{{ session('status') }}</p>
    @endif
    @if ($errors->has('solicitud'))
        <p class="aviso-error"><x-icono nombre="alerta" class="h-5 w-5 shrink-0" />{{ $errors->first('solicitud') }}</p>
    @endif

    <a href="{{ $esPersonal ? route('prestamos.pendientes') : route('prestamos.index') }}" class="enlace inline-flex items-center gap-1 text-sm">
        <x-icono nombre="izquierda" class="h-4 w-4" />{{ $esPersonal ? 'Solicitudes por aprobar' : 'Mis solicitudes' }}
    </a>

    <header class="mt-5 flex flex-wrap items-start justify-between gap-4 border-b border-marca-linea pb-5">
        <div class="flex min-w-0 items-start gap-4">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-marca-limaclaro text-marca-oliva ring-2 ring-marca-lima">
                <x-icono :nombre="$equipo->categoria->iconoVisible()" class="h-6 w-6" />
            </span>
            <div class="min-w-0">
                <h2 class="font-display text-2xl font-extrabold leading-tight text-marca-noche">{{ $equipo->nombre }}</h2>
                <p class="text-sm text-marca-suave">{{ $equipo->categoria->nombre }} · {{ $equipo->codigo_inventario }}@if ($equipo->ubicacion) · {{ $equipo->ubicacion }}@endif</p>
            </div>
        </div>
        <span class="insignia insignia-{{ $prestamo->estado }} normal-case text-sm">{{ ucfirst($nombresEstado[$prestamo->estado] ?? $prestamo->estado) }}</span>
    </header>

    @php($caja = 'rounded-2xl border border-marca-linea bg-marca-crema/60 px-4 py-3')
    <dl class="mt-6 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
        <div class="{{ $caja }}">
            <dt class="font-display text-xs font-bold uppercase tracking-wider text-marca-suave">Solicitante</dt>
            <dd class="mt-1 font-bold text-marca-noche">{{ $solicitante->name }}</dd>
            <dd class="mt-1"><span class="insignia insignia-{{ $solicitante->rol }}">{{ $solicitante->rol }}</span></dd>
        </div>
        <div class="{{ $caja }}">
            <dt class="font-display text-xs font-bold uppercase tracking-wider text-marca-suave">Carrera</dt>
            <dd class="mt-1 font-bold text-marca-noche">{{ $solicitante->programa?->nombre ?? '—' }}</dd>
            @if ($solicitante->programa)
                <dd class="text-xs text-marca-suave">{{ App\Models\Programa::etiquetaSede($solicitante->programa->sede) }}</dd>
            @endif
        </div>
        <div class="{{ $caja }}">
            <dt class="font-display text-xs font-bold uppercase tracking-wider text-marca-suave">Día y bloque</dt>
            <dd class="mt-1 font-bold text-marca-noche">{{ ucfirst($prestamo->fechaLarga()) }}</dd>
            <dd class="text-marca-noche">{{ $prestamo->bloque() }}</dd>
        </div>
        <div class="{{ $caja }}">
            <dt class="font-display text-xs font-bold uppercase tracking-wider text-marca-suave">Solicitada</dt>
            <dd class="mt-1 font-bold text-marca-noche">{{ ucfirst($cuando($prestamo->created_at)) }}</dd>
            <dd class="text-xs text-marca-suave">{{ $prestamo->created_at->locale('es')->diffForHumans() }}</dd>
        </div>
    </dl>

    @if ($prestamo->estado !== 'pendiente')
        <div class="mt-4 rounded-2xl border border-marca-linea px-4 py-3 text-sm text-marca-noche">
            @if ($prestamo->estado === 'aprobado')
                {{ $prestamo->aprobacion === 'automatica' ? 'Se aprobó automáticamente porque estaba libre.' : 'La aprobó '.($prestamo->funcionario?->name ?? 'un funcionario').' el '.$cuando($prestamo->fecha_respuesta).'.' }}
            @elseif ($prestamo->estado === 'rechazado')
                <b>Rechazada</b>{{ $prestamo->funcionario ? ' por '.$prestamo->funcionario->name : '' }} el {{ $cuando($prestamo->fecha_respuesta) }}.
                <span class="mt-1 block"><b>Motivo:</b> {{ $prestamo->motivo_rechazo }}</span>
            @elseif ($prestamo->estado === 'cancelado')
                El solicitante la canceló el {{ $cuando($prestamo->cancelado_en) }}.
            @endif
        </div>
    @endif

    @if ($puedeAtender)
        {{-- ---------- Otras solicitudes del mismo bloque ---------- --}}
        @if ($choques->isNotEmpty())
            <section class="mt-6 rounded-3xl border-2 border-amber-300 bg-amber-50 p-5" aria-labelledby="choques-titulo">
                <h3 id="choques-titulo" class="flex items-center gap-2 font-display text-base font-extrabold text-amber-900">
                    <x-icono nombre="alerta" class="h-5 w-5" />
                    {{ $choques->count() === 1 ? 'Hay otra solicitud' : 'Hay '.$choques->count().' solicitudes más' }} de «{{ $equipo->nombre }}» para este mismo día y bloque
                </h3>
                <ul class="mt-3 space-y-2">
                    @foreach ($choques as $otra)
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-2xl bg-white px-4 py-2.5 text-sm text-marca-noche ring-1 ring-inset ring-amber-200">
                            <span>
                                <b>{{ $otra->solicitante->name }}</b>
                                @if ($otra->solicitante->programa) <span class="text-marca-suave">({{ $otra->solicitante->programa->nombre }})</span> @endif
                                la creó el {{ $cuando($otra->created_at) }}
                                @if ($otra->created_at->lt($prestamo->created_at)) <span class="font-bold text-amber-800">· antes que esta</span> @endif
                            </span>
                            <span class="flex items-center gap-2">
                                <span class="insignia insignia-{{ $otra->estado }}">{{ $otra->estado }}</span>
                                <a class="enlace text-xs" href="{{ route('prestamos.show', $otra) }}">Ver</a>
                            </span>
                        </li>
                    @endforeach
                </ul>
                @if ($pendientesQueSeCruzan->isNotEmpty())
                    <p class="mt-3 text-sm text-amber-900">Si apruebas esta, {{ $pendientesQueSeCruzan->count() === 1 ? 'la otra pendiente se rechazará' : 'las otras '.$pendientesQueSeCruzan->count().' pendientes se rechazarán' }} automáticamente y se les avisará.</p>
                @endif
            </section>
        @endif

        {{-- ---------- Aprobar o rechazar ---------- --}}
        <div class="mt-6 flex flex-wrap items-start gap-4 border-t border-marca-linea pt-6" x-data="{ rechazar: @js($errors->has('motivo')) }">
            <form method="POST" action="{{ route('prestamos.aprobar', $prestamo) }}" x-show="! rechazar"
                  @if ($pendientesQueSeCruzan->isNotEmpty()) onsubmit="return confirm('Al aprobar esta solicitud se rechazarán automáticamente las otras pendientes del mismo bloque. ¿Continuar?');" @endif>
                @csrf
                @method('PATCH')
                <x-primary-button><x-icono nombre="check" class="h-4 w-4" />Aprobar</x-primary-button>
            </form>
            <x-danger-button type="button" x-show="! rechazar" x-on:click="rechazar = true; $nextTick(() => $refs.motivo.focus())"><x-icono nombre="cerrar" class="h-4 w-4" />Rechazar</x-danger-button>

            <form method="POST" action="{{ route('prestamos.rechazar', $prestamo) }}" class="w-full max-w-2xl" x-show="rechazar" x-cloak>
                @csrf
                @method('PATCH')
                <label for="motivo" class="block font-display text-sm font-bold text-marca-noche">Motivo del rechazo<x-obligatorio /></label>
                <textarea id="motivo" name="motivo" rows="3" x-ref="motivo" maxlength="500" required class="campo-uts mt-1 block w-full"
                          placeholder="Por ejemplo: el salón está reservado para una actividad institucional.">{{ old('motivo') }}</textarea>
                <p class="mt-1 text-xs text-marca-suave">El estudiante lo verá en su notificación.</p>
                <x-input-error :messages="$errors->get('motivo')" class="mt-2" />
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <x-danger-button>Rechazar solicitud</x-danger-button>
                    <button type="button" class="enlace text-sm" x-on:click="rechazar = false">Volver</button>
                </div>
            </form>
        </div>
    @elseif (! $esPersonal && $prestamo->sePuedeCancelar())
        <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-marca-linea pt-6">
            <form method="POST" action="{{ route('prestamos.cancelar', $prestamo) }}" onsubmit="return confirm('¿Cancelar esta solicitud?');">
                @csrf
                @method('PATCH')
                <x-danger-button><x-icono nombre="cerrar" class="h-4 w-4" />Cancelar solicitud</x-danger-button>
            </form>
            <span class="text-xs text-marca-suave">Puedes cancelarla hasta el {{ $prestamo->limiteCancelacion()->format('d/m/Y \a \l\a\s g:i a') }}.</span>
        </div>
    @endif
</x-modulo>
