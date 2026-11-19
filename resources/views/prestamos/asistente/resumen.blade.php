{{-- Resumen lateral «Tu solicitud»: lo elegido en cada paso, con un enlace para cambiarlo (vuelve a ese paso
     y borra lo elegido después), y las reglas que aplican. --}}
@php
    use App\Models\Configuracion;

    $filas = [
        ['calendario', 'Día', $fecha ? ucfirst($fecha->locale('es')->isoFormat('dddd D [de] MMMM')) : null, $irA['dia']],
        ['solicitud', 'Qué necesitas', $tipo ? ($tipo === 'espacio' ? 'Un espacio' : 'Un equipo') : null, $irA['que']],
        ['filtro', 'Categoría', $buscar !== '' && ! $equipo ? 'Búsqueda: «'.$buscar.'»' : ($categoria?->nombre ?? ($todas ? 'Todas' : null)), $irA['categoria']],
        [$tipo === 'espacio' ? 'salon' : 'equipos', $tipo ? ucfirst($nombreTipo) : 'Equipo o espacio', $equipo?->nombre, $irA['lista']],
    ];
@endphp

<aside class="h-fit rounded-3xl border border-marca-linea bg-marca-crema/60 p-5 lg:sticky lg:top-6" aria-labelledby="titulo-resumen">
    <h2 id="titulo-resumen" class="font-display text-lg font-extrabold text-marca-noche">Tu solicitud</h2>

    <dl class="mt-4 space-y-3">
        @foreach ($filas as [$icono, $etiqueta, $valor, $cambiar])
            <div class="flex items-start gap-3">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $valor ? 'bg-marca-bosque text-white' : 'bg-white text-marca-gris ring-1 ring-marca-linea' }}">
                    <x-icono :nombre="$icono" class="h-4 w-4" />
                </span>
                <div class="min-w-0 flex-1">
                    <dt class="text-[11px] font-bold uppercase tracking-wider text-marca-suave">{{ $etiqueta }}</dt>
                    <dd class="text-sm {{ $valor ? 'font-semibold text-marca-noche' : 'text-marca-suave' }}">{{ $valor ?? 'Por elegir' }}</dd>
                </div>
                @if ($valor)
                    <a href="{{ $cambiar }}" class="enlace shrink-0 text-xs">Cambiar</a>
                @endif
            </div>
        @endforeach
    </dl>

    @if ($tipo)
        <ul class="mt-5 space-y-2 border-t border-marca-linea pt-4 text-xs text-marca-noche">
            {{-- El texto va dentro de un span: en un contenedor flex, cada pedazo suelto se separaría. --}}
            <li class="flex items-start gap-2"><x-icono nombre="info" class="h-4 w-4 shrink-0 text-marca-oliva" />
                <span>
                    @if ($tipo === 'espacio')
                        Los espacios pasan por <b>aprobación del funcionario</b>. Te avisaremos cuando responda.
                    @else
                        Si el bloque está libre, tu préstamo queda <b>aprobado de una vez</b>.
                    @endif
                </span>
            </li>
            <li class="flex items-start gap-2"><x-icono nombre="reloj" class="h-4 w-4 shrink-0 text-marca-oliva" />
                <span>Pídelo con al menos <b>{{ Configuracion::duracion(Configuracion::minutosAnticipacion($usuario, $tipo === 'espacio')) }}</b> de anticipación. Puedes cancelarlo hasta <b>{{ Configuracion::duracion(Configuracion::minutosCancelacion($usuario)) }}</b> antes de la hora de inicio.</span>
            </li>
        </ul>
    @else
        <p class="mt-5 border-t border-marca-linea pt-4 text-xs text-marca-suave">Cada solicitud es de un solo espacio o equipo. Si necesitas varias cosas, haz una solicitud por cada una.</p>
    @endif
</aside>
