{{-- Paso 5: el bloque y la confirmación. Conserva el aviso cuando ya tiene préstamos en curso. --}}
@php
    $esEspacio = $equipo->esEspacio();
    $anticipacion = \App\Models\Configuracion::minutosAnticipacion($usuario, $esEspacio);
    $enCurso = $revision['enCurso'];
    $confirmar = session('confirmar') && $revision['avisar'] && $horarioElegido;
    $etiquetas = [
        'libre' => ['Libre', 'text-emerald-700'],
        'ocupado' => ['Ocupado', 'text-rose-700'],
        'propio' => ['Ya lo pediste', 'text-marca-oliva'],
        'pasado' => ['Ya pasó', 'text-marca-suave'],
        'anticipacion' => ['Muy pronto para pedirlo', 'text-amber-700'],
    ];
    $hayLibres = $bloques->contains('estado', 'libre');
    $elegido = $horarioElegido ?: optional($bloques->firstWhere('estado', 'libre'))['horario']?->id;
    $bloqueElegido = $bloques->first(fn ($b) => $b['horario']->id === $elegido && $b['estado'] === 'libre');
    $diaLargo = $fecha->locale('es')->isoFormat('dddd D [de] MMMM');
@endphp

<h2 class="mt-1 font-display text-2xl font-extrabold text-marca-noche">Elige el bloque</h2>
<p class="mt-1 text-sm text-marca-suave">
    <b class="text-marca-noche">{{ $equipo->nombre }}</b> · {{ $equipo->categoria->nombre }} · {{ $equipo->codigo_inventario }}
    @if ($esEspacio && $equipo->ubicacion) · {{ $equipo->ubicacion }} @endif
    @if ($esEspacio && $equipo->capacidad) · {{ $equipo->capacidad }} personas @endif
</p>

@if ($confirmar)
    {{-- ---------- Aviso: ya tiene préstamos en curso ---------- --}}
    <section class="mt-5 rounded-3xl border-2 border-marca-lima bg-white p-5 shadow-[0_14px_30px_-18px_rgba(11,61,42,0.5)]" role="alertdialog" aria-labelledby="aviso-titulo">
        <h3 id="aviso-titulo" class="flex items-center gap-2 font-display text-lg font-extrabold text-marca-noche">
            <x-icono nombre="alerta" class="h-6 w-6 text-amber-600" />
            @if ($revision['motivo'] === 'espacio')
                Ya tienes {{ $enCurso->count() === 1 ? 'una solicitud de espacio en curso' : $enCurso->count().' solicitudes de espacios en curso' }}
            @else
                Ya tienes {{ $enCurso->where('estado', 'aprobado')->count() === 1 ? 'un equipo aprobado' : $enCurso->where('estado', 'aprobado')->count().' equipos aprobados' }}
            @endif
        </h3>
        <ul class="mt-3 space-y-2">
            @foreach ($enCurso as $actualPrestamo)
                <li class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-marca-crema/70 px-4 py-2.5 text-sm">
                    <span><b>{{ $actualPrestamo->equipo->nombre }}</b> · {{ $actualPrestamo->fechaLarga() }} · {{ $actualPrestamo->bloque() }}</span>
                    <span class="insignia insignia-{{ $actualPrestamo->estado }} normal-case">{{ $actualPrestamo->estado === 'pendiente' ? 'Pendiente de aprobación' : 'Aprobado' }}</span>
                </li>
            @endforeach
        </ul>
        <p class="mt-3 text-sm text-marca-noche">
            @if ($revision['motivo'] === 'espacio')
                Si continúas, esta solicitud de <b>{{ $equipo->nombre }}</b> también pasará a <b>aprobación del funcionario</b>.
            @else
                Si continúas, la solicitud de <b>{{ $equipo->nombre }}</b> quedará <b>pendiente de aprobación</b> y se le avisará al funcionario.
                También puedes cancelar el que ya tienes y pedir este.
            @endif
        </p>
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <form method="POST" action="{{ route('prestamos.store') }}">
                @csrf
                <input type="hidden" name="equipo_id" value="{{ $equipo->id }}">
                <input type="hidden" name="fecha" value="{{ $fecha->toDateString() }}">
                <input type="hidden" name="horario_id" value="{{ $horarioElegido }}">
                <input type="hidden" name="confirmado" value="1">
                <x-primary-button>Continuar y enviar a aprobación</x-primary-button>
            </form>
            @if ($revision['motivo'] === 'maximo')
                @foreach ($enCurso->where('estado', 'aprobado')->filter->sePuedeCancelar() as $actualPrestamo)
                    <form method="POST" action="{{ route('prestamos.cancelar', $actualPrestamo) }}" onsubmit="return confirm('¿Cancelar {{ e($actualPrestamo->equipo->nombre) }} del {{ $actualPrestamo->fechaLarga() }}?');">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="volver[equipo]" value="{{ $equipo->id }}">
                        <input type="hidden" name="volver[fecha]" value="{{ $fecha->toDateString() }}">
                        <input type="hidden" name="volver[horario]" value="{{ $horarioElegido }}">
                        <x-secondary-button type="submit">Cancelar «{{ $actualPrestamo->equipo->nombre }}»</x-secondary-button>
                    </form>
                @endforeach
            @endif
            <a class="enlace text-sm" href="{{ $url() }}">Volver</a>
        </div>
    </section>
@endif

{{-- Bloques del día --}}
<section class="mt-5" aria-labelledby="titulo-bloques">
    <h3 id="titulo-bloques" class="flex flex-wrap items-center gap-2 font-display text-base font-bold text-marca-noche">
        <x-icono nombre="calendario" class="h-5 w-5 text-marca-oliva" />{{ ucfirst($diaLargo) }}
        <a href="{{ $irA['dia'] }}" class="enlace text-xs font-semibold">Cambiar el día</a>
    </h3>

    @if ($bloques->isEmpty())
        <p class="mt-3 rounded-2xl border-2 border-dashed border-marca-linea px-4 py-6 text-center text-sm text-marca-suave">
            {{ $equipo->nombre }} no se presta este día. <a href="{{ $irA['dia'] }}" class="enlace">Elige otro día</a>.
        </p>
    @else
        <form method="POST" action="{{ route('prestamos.store') }}" class="mt-3" x-data="{ bloque: @js($bloqueElegido ? $bloqueElegido['horario']->etiqueta() : null) }">
            @csrf
            <input type="hidden" name="equipo_id" value="{{ $equipo->id }}">
            <input type="hidden" name="fecha" value="{{ $fecha->toDateString() }}">

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($bloques as ['horario' => $horario, 'estado' => $estadoBloque])
                    @php $libre = $estadoBloque === 'libre'; @endphp
                    <label @class([
                        'flex items-center gap-3 rounded-2xl border-2 px-4 py-3 transition',
                        'cursor-pointer border-marca-linea bg-white hover:border-marca-lima has-[:checked]:border-marca-bosque has-[:checked]:bg-marca-limaclaro has-[:checked]:shadow' => $libre,
                        'cursor-not-allowed border-marca-linea bg-marca-crema/60 opacity-70' => ! $libre,
                    ])>
                        <input type="radio" name="horario_id" value="{{ $horario->id }}" class="h-5 w-5 border-2 border-marca-linea text-marca-bosque focus:ring-marca-lima"
                               x-on:change="bloque = @js($horario->etiqueta())"
                               @disabled(! $libre) @checked($libre && $elegido === $horario->id) required>
                        <span class="min-w-0">
                            <span class="block whitespace-nowrap font-display text-[15px] font-extrabold tabular-nums text-marca-noche">{{ $horario->etiqueta() }}</span>
                            <span class="block text-xs font-bold {{ $etiquetas[$estadoBloque][1] }}">
                                {{ $etiquetas[$estadoBloque][0] }}@if ($estadoBloque === 'anticipacion'): se pide con {{ \App\Models\Configuracion::duracion($anticipacion) }} de anticipación @endif
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>

            @if ($revision['avisar'] && ! $confirmar)
                <p class="mt-4 flex items-start gap-2 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-inset ring-amber-200">
                    <x-icono nombre="alerta" class="mt-0.5 h-5 w-5 shrink-0" />
                    Ya tienes {{ $esEspacio ? 'solicitudes de espacios en curso' : 'el máximo de equipos aprobados' }}. Al enviar te mostraremos cuáles para que decidas si continuar.
                </p>
            @endif

            {{-- Confirmación --}}
            <div class="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-3xl bg-marca-noche p-5 text-white">
                <p class="min-w-0 text-sm">
                    <span class="block text-xs font-bold uppercase tracking-wider text-marca-lima">Vas a solicitar</span>
                    <span class="mt-1 block font-display text-base font-extrabold">{{ $equipo->nombre }}</span>
                    <span class="block text-white/80">
                        {{ ucfirst($diaLargo) }} ·
                        <span x-text="bloque ?? 'elige un bloque'">{{ $bloqueElegido ? $bloqueElegido['horario']->etiqueta() : 'elige un bloque' }}</span>
                    </span>
                </p>
                <div class="flex flex-col items-end gap-1">
                    <button type="submit" @disabled(! $hayLibres) class="boton-claro disabled:cursor-not-allowed disabled:opacity-50">
                        {{ $esEspacio || $revision['requiereAprobacion'] ? 'Enviar a aprobación' : 'Solicitar' }} <span aria-hidden="true">→</span>
                    </button>
                    @unless ($hayLibres)
                        <span class="text-xs text-white/70">No hay bloques libres este día. Elige otro día.</span>
                    @endunless
                </div>
            </div>
        </form>
    @endif
</section>
