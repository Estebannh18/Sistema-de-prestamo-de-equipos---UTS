@php
    use App\Models\Configuracion;

    $iconos = ['maximos' => 'check', 'cancelacion' => 'cerrar', 'anticipacion' => 'reloj'];
    $tituloSeccion = 'flex items-center gap-2 font-display text-base font-bold text-marca-noche';
    $iconoSeccion = 'flex h-8 w-8 items-center justify-center rounded-full bg-marca-limaclaro text-marca-oliva';
    $porGrupo = collect(Configuracion::VALORES)->groupBy(fn ($d) => $d[0], preserveKeys: true);
@endphp

<x-modulo titulo="Configuración" :pendiente="false">
    @if (session('status'))
        <p class="aviso-ok"><x-icono nombre="check" class="h-5 w-5 shrink-0" />{{ session('status') }}</p>
    @endif

    <p class="text-sm text-marca-suave">Reglas de las solicitudes de préstamo. Los cambios se aplican a las solicitudes nuevas; las que ya existen no cambian.</p>

    <form method="POST" action="{{ route('configuracion.update') }}" class="mt-6 space-y-8" novalidate>
        @csrf
        @method('PUT')

        @foreach (Configuracion::GRUPOS as $grupo => [$titulo, $explicacion])
            <fieldset @class(['border-t border-marca-linea pt-6' => ! $loop->first])>
                <legend class="{{ $tituloSeccion }} float-left w-full">
                    <span class="{{ $iconoSeccion }}"><x-icono :nombre="$iconos[$grupo]" class="h-4 w-4" /></span>
                    {{ $titulo }}
                </legend>
                <p class="clear-both pt-2 text-xs text-marca-suave">{{ $explicacion }}</p>

                <div class="mt-4 grid gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($porGrupo[$grupo] as $clave => [, $etiqueta, $unidad, , $min, $max])
                        @php($valor = old($clave, $valores[$clave]))
                        <div x-data="{ valor: @js((string) $valor) }">
                            <label for="{{ $clave }}" class="block font-display text-sm font-bold text-marca-noche">{{ $etiqueta }}<x-obligatorio /></label>
                            <div class="relative mt-1">
                                <x-text-input id="{{ $clave }}" name="{{ $clave }}" type="number" :min="$min" :max="$max" step="1" inputmode="numeric" required
                                              class="block w-full pr-20 tabular-nums" :value="$valor" x-model="valor" />
                                <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-xs font-bold text-marca-suave">
                                    {{ $unidad === 'minutos' ? 'minutos' : 'aprobados' }}
                                </span>
                            </div>
                            @if ($unidad === 'minutos')
                                {{-- Se muestra en horas para leerlo fácil: 90 → 1 h 30 min. --}}
                                <p class="mt-1 text-xs font-semibold text-marca-oliva" x-show="valor !== '' && Number(valor) >= 60" x-cloak
                                   x-text="'= ' + Math.floor(valor / 60) + ' h' + (valor % 60 ? ' ' + (valor % 60) + ' min' : '')"></p>
                            @endif
                            <x-input-error :messages="$errors->get($clave)" class="mt-2" />
                        </div>
                    @endforeach
                </div>
            </fieldset>
        @endforeach

        <div class="flex flex-wrap items-center gap-4 border-t border-marca-linea pt-6">
            <x-primary-button>Guardar configuración</x-primary-button>
        </div>
    </form>
</x-modulo>
