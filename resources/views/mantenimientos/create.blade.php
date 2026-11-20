{{--
    Programar el mantenimiento de un espacio o equipo. Si hay solicitudes en esas fechas ($afectadas),
    se muestran y se pide confirmar: al continuar se cancelan con el motivo y se avisa a cada solicitante.
--}}
@php($confirmando = $afectadas->isNotEmpty())

<x-modulo titulo="Programar mantenimiento" :pendiente="false">
    <a href="{{ route('equipos.show', $equipo) }}" class="enlace inline-flex items-center gap-1 text-sm"><x-icono nombre="izquierda" class="h-4 w-4" />{{ $equipo->nombre }}</a>

    <div class="mt-5 flex items-start gap-3">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-700"><x-icono nombre="herramienta" class="h-5 w-5" /></span>
        <div>
            <h2 class="font-display text-xl font-extrabold text-marca-noche">{{ $equipo->nombre }}</h2>
            <p class="text-sm text-marca-suave">{{ $equipo->categoria->nombre }} · {{ $equipo->codigo_inventario }}</p>
        </div>
    </div>

    <p class="mt-4 max-w-2xl text-sm text-marca-suave">
        En esas fechas nadie lo podrá pedir. Si no pones fecha de fin, queda en mantenimiento hasta que alguien lo termine.
        Si ya hay solicitudes en esas fechas, te mostraremos cuáles antes de guardar.
    </p>

    <form method="POST" action="{{ route('mantenimientos.store', $equipo) }}" class="mt-6 max-w-2xl" novalidate>
        @csrf

        @if ($confirmando)
            {{-- ---------- Aviso: solicitudes que se cancelarán ---------- --}}
            <section class="mb-6 rounded-3xl border-2 border-amber-300 bg-amber-50 p-5" role="alertdialog" aria-labelledby="aviso-titulo">
                <h3 id="aviso-titulo" class="flex items-center gap-2 font-display text-base font-extrabold text-amber-900">
                    <x-icono nombre="alerta" class="h-5 w-5" />
                    {{ $afectadas->count() === 1 ? 'Hay 1 solicitud' : 'Hay '.$afectadas->count().' solicitudes' }} en esas fechas
                </h3>
                <ul class="mt-3 space-y-2">
                    @foreach ($afectadas as $prestamo)
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-2xl bg-white px-4 py-2.5 text-sm text-marca-noche ring-1 ring-inset ring-amber-200">
                            <span><b>{{ $prestamo->solicitante->name }}</b> ({{ $prestamo->solicitante->rol }}) · {{ ucfirst($prestamo->fechaLarga()) }} · {{ $prestamo->bloque() }}</span>
                            <span class="insignia insignia-{{ $prestamo->estado }}">{{ $prestamo->estado }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-sm text-amber-900">
                    Si lo pones en mantenimiento, {{ $afectadas->count() === 1 ? 'esta solicitud se cancelará' : 'estas solicitudes se cancelarán' }} automáticamente con el motivo que escribiste,
                    y a cada solicitante le llegará el aviso. ¿Deseas continuar?
                </p>
                {{-- La confirmación vale solo para estas fechas: si se cambian, se vuelve a mostrar el aviso. --}}
                <input type="hidden" name="confirmado" value="{{ request('desde') }}|{{ request('hasta') }}">
            </section>
        @endif

        <div class="grid gap-x-6 gap-y-5 sm:grid-cols-2">
            <div>
                <x-input-label for="desde">Desde<x-obligatorio /></x-input-label>
                <input id="desde" name="desde" type="date" min="{{ today()->toDateString() }}" value="{{ old('desde', request('desde')) }}" required class="campo-uts mt-1 block w-full">
                <x-input-error :messages="$errors->get('desde')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="hasta" value="Hasta (opcional)" />
                <input id="hasta" name="hasta" type="date" min="{{ today()->toDateString() }}" value="{{ old('hasta', request('hasta')) }}" class="campo-uts mt-1 block w-full">
                <p class="mt-1 text-xs text-marca-suave">Vacío: sin fecha de fin.</p>
                <x-input-error :messages="$errors->get('hasta')" class="mt-2" />
            </div>
        </div>

        <div class="mt-5">
            <x-input-label for="motivo">Motivo<x-obligatorio /></x-input-label>
            <textarea id="motivo" name="motivo" rows="3" maxlength="500" required class="campo-uts mt-1 block w-full"
                      placeholder="Por ejemplo: cambio de lámpara del video beam.">{{ old('motivo', request('motivo')) }}</textarea>
            <p class="mt-1 text-xs text-marca-suave">Lo verán quienes tengan solicitudes en esas fechas.</p>
            <x-input-error :messages="$errors->get('motivo')" class="mt-2" />
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            @if ($confirmando)
                <x-danger-button>Sí, programar y cancelar {{ $afectadas->count() === 1 ? 'la solicitud' : 'las '.$afectadas->count().' solicitudes' }}</x-danger-button>
            @else
                <x-primary-button><x-icono nombre="herramienta" class="h-4 w-4" />Programar mantenimiento</x-primary-button>
            @endif
            <a class="enlace text-sm" href="{{ route('equipos.show', $equipo) }}">Volver</a>
        </div>
    </form>
</x-modulo>
