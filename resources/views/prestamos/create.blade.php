{{--
    Pestaña Solicitud: un formulario por pasos para no tener que buscar en todo el catálogo.
      1. dia        ¿Para qué día?
      2. que        ¿Equipo o espacio?
      3. categoria  ¿Qué tipo? (categorías y buscador)
      4. lista      ¿Cuál? (resultados con filtros y bloques libres de ese día)
      5. horario    El bloque y la confirmación
    Lo elegido viaja en la dirección (fecha, tipo, categoria, q, equipo); $url() arma el enlace
    de cada paso cambiando solo lo que se indica. Cada solicitud es de un solo espacio o equipo.
--}}
@php
    $usuario = Auth::user();

    $estado = [
        'fecha' => $fecha?->toDateString(),
        'tipo' => $tipo,
        'categoria' => $todas ? 'todas' : $categoria?->id,
        'q' => $buscar !== '' ? $buscar : null,
        'equipo' => $equipo?->id,
        'libres' => $soloLibres ? 1 : null,
    ];
    $url = fn (array $cambios = []) => route('prestamos.create', array_filter(array_merge($estado, $cambios), fn ($valor) => $valor !== null && $valor !== ''));
    $nombreTipo = $tipo === 'espacio' ? 'espacio' : 'equipo';

    // Volver a un paso borra lo elegido en ese paso y en los siguientes: si cambia el día,
    // vuelve a elegir todo; si vuelve a la categoría, vuelve a elegir el equipo y el bloque.
    $irA = [
        'dia' => route('prestamos.create'),
        'que' => $url(['tipo' => null, 'categoria' => null, 'q' => null, 'equipo' => null, 'libres' => null]),
        'categoria' => $url(['categoria' => null, 'q' => null, 'equipo' => null, 'libres' => null]),
        'lista' => $url(['equipo' => null]),
    ];

    // Barra de pasos: [clave, nombre]. Los anteriores al actual se pueden abrir con un clic.
    $pasos = [
        ['dia', 'Día'],
        ['que', 'Equipo o espacio'],
        ['categoria', 'Categoría'],
        ['lista', $tipo ? ucfirst($nombreTipo) : 'Elección'],
        ['horario', 'Horario'],
    ];
    $actual = array_search($paso, array_column($pasos, 0), true);
    $atras = $actual > 0 ? $irA[$pasos[$actual - 1][0]] : null;
@endphp

<x-modulo titulo="Nueva solicitud" :pendiente="false">
    @if (session('status'))
        <p class="aviso-ok"><x-icono nombre="check" class="h-5 w-5 shrink-0" />{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="aviso-error !items-start">
            <x-icono nombre="alerta" class="h-5 w-5 shrink-0" />
            <ul class="space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <a href="{{ route('prestamos.index') }}" class="enlace mb-5 inline-flex items-center gap-1 text-sm"><x-icono nombre="izquierda" class="h-4 w-4" />Mis solicitudes</a>

    {{-- ---------- Barra de pasos: los completados se pueden abrir ---------- --}}
    <nav aria-label="Pasos de la solicitud" class="rounded-3xl bg-marca-crema/70 px-4 py-3 ring-1 ring-inset ring-marca-linea">
        <ol class="flex items-center gap-1 overflow-x-auto">
            @foreach ($pasos as $i => [$clave, $nombre])
                @php
                    $esActual = $i === $actual;
                    $listo = $i < $actual;
                @endphp
                <li class="flex shrink-0 items-center gap-1" @if ($esActual) aria-current="step" @endif>
                    @if ($listo)
                        <a href="{{ $irA[$clave] }}" title="Volver al paso {{ $i + 1 }}: {{ $nombre }}"
                           class="group flex items-center gap-2 rounded-full py-1 pl-1 pr-3 transition hover:bg-white focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/60">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-marca-bosque text-white transition group-hover:bg-marca-oliva">
                                <x-icono nombre="check" class="h-4 w-4 group-hover:hidden" />
                                <span class="hidden font-display text-sm font-extrabold group-hover:inline">{{ $i + 1 }}</span>
                            </span>
                            <span class="hidden text-sm font-bold text-marca-bosque group-hover:underline sm:inline">{{ $nombre }}</span>
                        </a>
                    @else
                        <span class="flex items-center gap-2 py-1 pl-1 pr-3">
                            <span @class([
                                'flex h-8 w-8 shrink-0 items-center justify-center rounded-full font-display text-sm font-extrabold',
                                'bg-marca-lima text-marca-noche ring-4 ring-white' => $esActual,
                                'bg-white text-marca-gris ring-1 ring-inset ring-marca-linea' => ! $esActual,
                            ])>{{ $i + 1 }}</span>
                            <span @class([
                                'text-sm font-bold',
                                'text-marca-noche' => $esActual,
                                'hidden text-marca-gris sm:inline' => ! $esActual,
                            ])>{{ $nombre }}</span>
                        </span>
                    @endif
                    @if (! $loop->last)
                        <span class="h-0.5 w-4 rounded-full sm:w-8 {{ $listo ? 'bg-marca-bosque' : 'bg-marca-linea' }}" aria-hidden="true"></span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>

    <div class="mt-6 grid gap-8 lg:grid-cols-[minmax(0,1fr)_19rem]">
        <div class="min-w-0">
            @if ($atras)
                <a href="{{ $atras }}" class="enlace mb-4 inline-flex items-center gap-1 text-sm"><x-icono nombre="izquierda" class="h-4 w-4" />Atrás</a>
            @endif
            <p class="text-xs font-bold uppercase tracking-wider text-marca-oliva">Paso {{ $actual + 1 }} de {{ count($pasos) }}</p>

            @include('prestamos.asistente.'.$paso)
        </div>

        @include('prestamos.asistente.resumen')
    </div>
</x-modulo>
