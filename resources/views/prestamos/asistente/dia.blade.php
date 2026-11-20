{{--
    Paso 1: el día, en un calendario por meses (domingo a sábado). Solo se pueden elegir los días
    de $fechas: desde hoy hasta DIAS_VISIBLES días adelante, sin domingos ni festivos.
    Si ya eligió un espacio o equipo (llegó desde el catálogo), tampoco los días en que no se presta.
--}}
@php
    use App\Services\PrestamoService;
    use App\Support\Festivos;

    $disponibles = $fechas->map->toDateString()->flip();
    $ultimo = today()->addDays(PrestamoService::DIAS_VISIBLES);

    $meses = [];
    for ($mes = today()->startOfMonth(); $mes->lessThanOrEqualTo($ultimo); $mes = $mes->copy()->addMonth()) {
        $meses[] = $mes->copy();
    }

    // Festivos que caen dentro del periodo que se puede pedir, para explicarlos debajo del calendario.
    $festivosDelPeriodo = collect(range(0, PrestamoService::DIAS_VISIBLES))
        ->map(fn ($dias) => today()->addDays($dias))
        ->filter(fn ($dia) => Festivos::esFestivo($dia));

    $semana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
@endphp

<h2 class="mt-1 font-display text-2xl font-extrabold text-marca-noche">¿Para qué día lo necesitas?</h2>
<p class="mt-1 text-sm text-marca-suave">
    Puedes pedir desde hoy hasta el <b class="text-marca-noche">{{ $ultimo->locale('es')->isoFormat('dddd D [de] MMMM') }}</b>, de lunes a sábado, sin festivos.
    Cada solicitud es de <b class="text-marca-noche">un solo espacio o equipo</b>, para un día y un bloque de horas.
</p>

<div @class(['mt-5 grid gap-4', 'md:grid-cols-2' => count($meses) > 1])>
    @foreach ($meses as $mes)
        <section class="rounded-3xl border border-marca-linea bg-white p-4 sm:p-5" aria-label="{{ $mes->locale('es')->isoFormat('MMMM YYYY') }}">
            <h3 class="flex items-center gap-2 font-display text-lg font-extrabold text-marca-noche">
                <x-icono nombre="calendario" class="h-5 w-5 text-marca-oliva" />{{ ucfirst($mes->locale('es')->isoFormat('MMMM [de] YYYY')) }}
            </h3>

            <div class="mt-3 grid grid-cols-7 gap-1 text-center" role="grid">
                @foreach ($semana as $i => $nombreDia)
                    <span class="pb-1 text-[11px] font-bold uppercase tracking-wider {{ $i === 0 ? 'text-rose-400' : 'text-marca-suave' }}" role="columnheader">{{ $nombreDia }}</span>
                @endforeach

                {{-- Espacios antes del día 1 (la semana empieza en domingo) --}}
                @for ($i = 0; $i < $mes->dayOfWeek; $i++)
                    <span aria-hidden="true"></span>
                @endfor

                @for ($d = 1; $d <= $mes->daysInMonth; $d++)
                    @php
                        $dia = $mes->copy()->day($d);
                        $festivo = Festivos::nombre($dia);
                        $enRango = $dia->betweenIncluded(today(), $ultimo);
                        $sePresta = ! $equipo || in_array($dia->dayOfWeekIso, $diasConHorario ?? [], true);
                        $enMantenimiento = ($mantenimientos ?? collect())->first(fn ($m) => $dia->greaterThanOrEqualTo($m->desde) && ($m->hasta === null || $dia->lessThanOrEqualTo($m->hasta)));
                        $elegible = $disponibles->has($dia->toDateString()) && $sePresta && ! $enMantenimiento;
                        $motivo = match (true) {
                            $elegible => null,
                            ! $enRango => $dia->lessThan(today()) ? 'Ya pasó' : 'Fuera del rango que se puede pedir',
                            $festivo !== null => 'Festivo: '.$festivo,
                            $dia->isSunday() => 'Domingo: no hay préstamos',
                            $enMantenimiento !== null => 'En mantenimiento: '.$enMantenimiento->motivo,
                            default => ($equipo?->nombre ?? 'Este equipo').' no se presta este día',
                        };
                    @endphp
                    @if ($elegible)
                        <a href="{{ $url(['fecha' => $dia->toDateString()]) }}" role="gridcell"
                           aria-label="{{ ucfirst($dia->locale('es')->isoFormat('dddd D [de] MMMM')) }}{{ $dia->isToday() ? ' (hoy)' : '' }}"
                           class="relative flex aspect-square items-center justify-center rounded-xl border-2 font-display text-base font-extrabold transition duration-150 focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/60
                                  {{ $dia->isToday() ? 'border-marca-lima bg-marca-limaclaro text-marca-noche' : 'border-marca-linea bg-white text-marca-noche' }} hover:-translate-y-0.5 hover:border-marca-bosque hover:bg-marca-bosque hover:text-white hover:shadow-[0_8px_16px_-10px_rgba(11,61,42,0.6)]">
                            {{ $d }}
                            @if ($dia->isToday())
                                <span class="absolute bottom-0.5 text-[9px] font-bold uppercase leading-none">Hoy</span>
                            @endif
                        </a>
                    @else
                        <span role="gridcell" aria-disabled="true" title="{{ $motivo }}"
                              @class([
                                  'relative flex aspect-square items-center justify-center rounded-xl text-sm font-semibold',
                                  'bg-rose-50 text-rose-400 line-through decoration-rose-300' => $festivo !== null && $enRango,
                                  'bg-amber-50 text-amber-500 line-through decoration-amber-300' => $festivo === null && $enRango && $enMantenimiento && ! $dia->isSunday(),
                                  'bg-marca-crema/70 text-marca-gris' => $festivo === null && $enRango && ! ($enMantenimiento && ! $dia->isSunday()),
                                  'text-marca-gris/50' => ! $enRango,
                              ])>
                            {{ $d }}
                            @if ($festivo !== null && $enRango)
                                <span class="sr-only">{{ $motivo }}</span>
                                <span class="absolute bottom-0.5 text-[8px] font-bold uppercase leading-none no-underline" aria-hidden="true">Festivo</span>
                            @endif
                        </span>
                    @endif
                @endfor
            </div>
        </section>
    @endforeach
</div>

{{-- Convenciones --}}
<ul class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-marca-suave">
    <li class="flex items-center gap-2"><span class="h-4 w-4 rounded-md border-2 border-marca-linea bg-white"></span>Disponible</li>
    <li class="flex items-center gap-2"><span class="h-4 w-4 rounded-md border-2 border-marca-lima bg-marca-limaclaro"></span>Hoy</li>
    <li class="flex items-center gap-2"><span class="h-4 w-4 rounded-md bg-rose-50 ring-1 ring-inset ring-rose-200"></span>Festivo</li>
    <li class="flex items-center gap-2"><span class="h-4 w-4 rounded-md bg-marca-crema ring-1 ring-inset ring-marca-linea"></span>Domingo o no disponible</li>
    @if (($mantenimientos ?? collect())->isNotEmpty())
        <li class="flex items-center gap-2"><span class="h-4 w-4 rounded-md bg-amber-50 ring-1 ring-inset ring-amber-200"></span>En mantenimiento</li>
    @endif
</ul>

@foreach ($mantenimientos ?? [] as $mantenimiento)
    <p class="mt-3 flex items-start gap-2 rounded-2xl bg-amber-50 px-4 py-2.5 text-sm text-amber-900 ring-1 ring-inset ring-amber-200">
        <x-icono nombre="herramienta" class="mt-0.5 h-4 w-4 shrink-0" />
        <span>{{ $equipo->nombre }} estará en mantenimiento {{ $mantenimiento->periodo() }}: {{ $mantenimiento->motivo }}</span>
    </p>
@endforeach

@if ($festivosDelPeriodo->isNotEmpty())
    <p class="mt-3 flex items-start gap-2 rounded-2xl bg-rose-50 px-4 py-2.5 text-sm text-rose-800 ring-1 ring-inset ring-rose-200">
        <x-icono nombre="info" class="mt-0.5 h-4 w-4 shrink-0" />
        <span>
            No hay préstamos en festivos:
            {{ $festivosDelPeriodo->map(fn ($dia) => $dia->locale('es')->isoFormat('dddd D [de] MMMM').' ('.Festivos::nombre($dia).')')->join(', ', ' y ') }}.
        </span>
    </p>
@endif
