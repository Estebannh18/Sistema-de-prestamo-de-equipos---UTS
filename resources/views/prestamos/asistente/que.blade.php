{{-- Paso 2: equipo o espacio. Cada tarjeta explica cómo se aprueba y la anticipación; se desactiva si no hay nada o si ese día ya no alcanza. --}}
@php
    use App\Models\Configuracion;

    $tarjetas = [
        'equipo' => ['Un equipo', 'portatil', 'Si el bloque está libre, queda aprobado de una vez.', 'No hay equipos disponibles para pedir en este momento.'],
        'espacio' => ['Un espacio', 'salon', 'Pasa por aprobación del funcionario.', 'Todavía no hay espacios habilitados para tu carrera'.($usuario->programa ? ' ('.$usuario->programa->nombre.')' : '').'.'],
    ];
@endphp
<h2 class="mt-1 font-display text-2xl font-extrabold text-marca-noche">¿Qué necesitas pedir?</h2>
<p class="mt-1 text-sm text-marca-suave">Para el {{ $fecha->locale('es')->isoFormat('dddd D [de] MMMM') }}.</p>

<div class="mt-5 grid gap-4 md:grid-cols-2">
    @foreach ($tarjetas as $clave => [$titulo, $icono, $aprobacion, $vacio])
        @php
            $info = $tipos[$clave];
            $sePuede = $info['cantidad'] > 0 && $info['alcanza'];
        @endphp
        @if ($sePuede)
            <a href="{{ $url(['tipo' => $clave]) }}" class="group flex flex-col rounded-3xl border-2 border-marca-linea bg-white p-6 transition duration-200 hover:-translate-y-1 hover:border-marca-lima hover:shadow-[0_18px_36px_-20px_rgba(11,61,42,0.55)] focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/60">
        @else
            <div class="flex flex-col rounded-3xl border-2 border-dashed border-marca-linea bg-marca-crema/50 p-6" aria-disabled="true">
        @endif
            <span @class([
                'flex h-16 w-16 items-center justify-center rounded-2xl transition',
                'bg-marca-lima text-marca-noche shadow-[0_6px_0_0_#7b8305] group-hover:-rotate-6' => $sePuede && $clave === 'equipo',
                'bg-marca-bosque text-marca-lima shadow-[0_6px_0_0_#06261a] group-hover:rotate-6' => $sePuede && $clave === 'espacio',
                'bg-white text-marca-gris ring-1 ring-marca-linea' => ! $sePuede,
            ])>
                <x-icono :nombre="$icono" class="h-8 w-8" />
            </span>
            <span class="mt-5 font-display text-xl font-extrabold {{ $sePuede ? 'text-marca-noche' : 'text-marca-suave' }}">{{ $titulo }}</span>
            @if ($info['ejemplos'])
                <span class="mt-1 text-sm text-marca-suave">{{ collect($info['ejemplos'])->join(', ', ' y ') }}{{ $info['cantidad'] > 0 ? ' · '.$info['cantidad'].' '.($info['cantidad'] === 1 ? 'opción' : 'opciones') : '' }}</span>
            @endif

            @if ($sePuede)
                <span class="mt-4 flex flex-col gap-1.5 border-t border-marca-linea pt-4 text-sm text-marca-noche">
                    <span class="flex items-start gap-2"><x-icono nombre="info" class="mt-0.5 h-4 w-4 shrink-0 text-marca-oliva" /><span>{{ $aprobacion }}</span></span>
                    <span class="flex items-start gap-2"><x-icono nombre="reloj" class="mt-0.5 h-4 w-4 shrink-0 text-marca-oliva" /><span>Se pide con al menos {{ Configuracion::duracion($info['anticipacion']) }} de anticipación.</span></span>
                </span>
                <span class="mt-5 inline-flex items-center gap-1 self-start font-display text-sm font-bold text-marca-oliva transition group-hover:gap-2">Elegir {{ $clave }} <x-icono nombre="derecha" class="h-4 w-4" /></span>
            @else
                <span class="mt-4 flex items-start gap-2 border-t border-marca-linea pt-4 text-sm font-semibold text-marca-suave">
                    <x-icono nombre="alerta" class="mt-0.5 h-4 w-4 shrink-0" />
                    <span>
                        @if ($info['cantidad'] === 0)
                            {{ $vacio }}
                        @else
                            Para este día ya no alcanza: se pide con {{ Configuracion::duracion($info['anticipacion']) }} de anticipación.
                            <a href="{{ $irA['dia'] }}" class="enlace">Elegir otro día</a>
                        @endif
                    </span>
                </span>
            @endif
        @if ($sePuede)
            </a>
        @else
            </div>
        @endif
    @endforeach
</div>
