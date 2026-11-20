@php($carreras = $equipo->carrerasHabilitadas())

<x-modulo :titulo="$equipo->nombre" :pendiente="false">
    @if (session('status'))
        <p class="aviso-ok"><x-icono nombre="check" class="h-5 w-5 shrink-0" />{{ session('status') }}</p>
    @endif
    @if ($errors->has('mantenimiento'))
        <p class="aviso-error"><x-icono nombre="alerta" class="h-5 w-5 shrink-0" />{{ $errors->first('mantenimiento') }}</p>
    @endif
    @if ($equipo->esDeLaCarreraDe(Auth::user()))
        <p class="mb-5 inline-flex items-center gap-1.5 rounded-full bg-marca-lima px-3 py-1 font-display text-sm font-bold text-marca-noche">
            <x-icono nombre="estrella" class="h-4 w-4" />Habilitado para tu carrera
        </p>
    @endif

    <dl class="grid max-w-2xl gap-x-6 gap-y-3 sm:grid-cols-[max-content_1fr]">
        <dt class="font-semibold text-marca-noche">Código</dt>
        <dd>{{ $equipo->codigo_inventario }}</dd>
        <dt class="font-semibold text-marca-noche">Categoría</dt>
        <dd>{{ $equipo->categoria->nombre }}</dd>
        <dt class="font-semibold text-marca-noche">Estado</dt>
        <dd><span class="insignia insignia-{{ $equipo->estado }}">{{ $equipo->estado }}</span></dd>
        <dt class="font-semibold text-marca-noche">Lo puede pedir</dt>
        <dd>{{ $equipo->quienPuedePedir() }}</dd>
        @if ($equipo->esEspacio())
            @if ($equipo->ubicacion)
                <dt class="font-semibold text-marca-noche">Ubicación</dt>
                <dd>{{ $equipo->ubicacion }}</dd>
            @endif
            @if ($equipo->capacidad)
                <dt class="font-semibold text-marca-noche">Capacidad</dt>
                <dd>{{ $equipo->capacidad }} personas</dd>
            @endif
            @if ($carreras->isNotEmpty())
                <dt class="font-semibold text-marca-noche">Carreras habilitadas</dt>
                <dd>
                    <ul class="flex flex-wrap gap-1.5">
                        @foreach ($carreras as $carrera)
                            <li class="rounded-full bg-marca-limaclaro px-2.5 py-0.5 text-xs font-semibold text-marca-bosque">{{ $carrera }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-1.5 text-xs text-marca-suave">Los estudiantes de estas carreras lo pueden pedir; los docentes, cualquier espacio.</p>
                </dd>
            @endif
        @else
            @if ($equipo->marca)
                <dt class="font-semibold text-marca-noche">Marca</dt>
                <dd>{{ $equipo->marca }}</dd>
            @endif
            @if ($equipo->modelo)
                <dt class="font-semibold text-marca-noche">Modelo</dt>
                <dd>{{ $equipo->modelo }}</dd>
            @endif
        @endif
        @if ($equipo->descripcion)
            <dt class="font-semibold text-marca-noche">Descripción</dt>
            <dd>{{ $equipo->descripcion }}</dd>
        @endif
    </dl>

    {{-- Horarios en que se presta, por día --}}
    @php($porDia = $equipo->horarios->groupBy('dia_semana'))
    <section class="mt-8 border-t border-marca-linea pt-6" aria-labelledby="horarios-titulo">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="horarios-titulo" class="flex items-center gap-2 font-display text-base font-bold text-marca-noche">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-marca-limaclaro text-marca-oliva"><x-icono nombre="reloj" class="h-4 w-4" /></span>
                Horarios en que se presta
            </h2>
            @if (Auth::user()->esAdministrador())
                <a class="boton-principal text-sm" href="{{ route('horarios.edit', $equipo) }}"><x-icono nombre="editar" class="h-4 w-4" />Configurar horarios</a>
            @elseif (Auth::user()->can('solicitar', App\Models\Prestamo::class) && $equipo->permiteSolicitud(Auth::user()) && $equipo->estaDisponible() && $porDia->isNotEmpty())
                <a class="boton-principal text-sm" href="{{ route('prestamos.create', ['equipo' => $equipo->id]) }}">Solicitar</a>
            @endif
        </div>
        @if ($porDia->isEmpty())
            <p class="mt-3 text-sm text-marca-suave">Todavía no tiene horarios: no se puede solicitar.</p>
        @else
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (App\Models\Horario::DIAS as $dia => $nombre)
                    <div class="rounded-2xl border border-marca-linea px-4 py-3">
                        <p class="font-display text-sm font-bold text-marca-noche">{{ $nombre }}</p>
                        @if ($porDia->has($dia))
                            <ul class="mt-1.5 flex flex-wrap gap-1.5">
                                @foreach ($porDia[$dia] as $horario)
                                    <li class="rounded-full bg-marca-crema px-2.5 py-0.5 text-xs font-semibold tabular-nums text-marca-noche ring-1 ring-inset ring-marca-linea">{{ $horario->etiqueta() }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-1 text-xs text-marca-suave">No se presta.</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Mantenimiento: actual y próximos. El personal lo programa y lo termina. --}}
    @php($esPersonal = Auth::user()->esAdministrador() || Auth::user()->esFuncionario())
    @if ($esPersonal || $equipo->mantenimientos->isNotEmpty())
        <section class="mt-8 border-t border-marca-linea pt-6" aria-labelledby="mantenimiento-titulo">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="mantenimiento-titulo" class="flex items-center gap-2 font-display text-base font-bold text-marca-noche">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-100 text-amber-700"><x-icono nombre="herramienta" class="h-4 w-4" /></span>
                    Mantenimiento
                </h2>
                @if ($esPersonal)
                    <a class="boton-claro text-sm" href="{{ route('mantenimientos.create', $equipo) }}"><x-icono nombre="mas" class="h-4 w-4" />Programar mantenimiento</a>
                @endif
            </div>
            @if ($equipo->mantenimientos->isEmpty())
                <p class="mt-3 text-sm text-marca-suave">No tiene mantenimientos programados.</p>
            @else
                <ul class="mt-4 space-y-2">
                    @foreach ($equipo->mantenimientos as $mantenimiento)
                        <li class="flex flex-wrap items-start justify-between gap-3 rounded-2xl border px-4 py-3 text-sm {{ $mantenimiento->yaEmpezo() ? 'border-amber-300 bg-amber-50' : 'border-marca-linea' }}">
                            <div class="min-w-0">
                                <p class="font-bold text-marca-noche">
                                    {{ $mantenimiento->yaEmpezo() ? ($mantenimiento->esIndefinido() ? 'Fuera de servicio' : 'En mantenimiento') : 'Programado' }}
                                    <span class="font-normal text-marca-suave">· {{ $mantenimiento->periodo() }}</span>
                                </p>
                                <p class="mt-0.5 text-marca-noche">{{ $mantenimiento->motivo }}</p>
                                @if ($esPersonal && $mantenimiento->creadoPor)
                                    <p class="mt-0.5 text-xs text-marca-suave">Lo programó {{ $mantenimiento->creadoPor->name }} el {{ $mantenimiento->created_at->format('d/m/Y') }}.</p>
                                @endif
                            </div>
                            @if ($esPersonal)
                                <form method="POST" action="{{ route('mantenimientos.terminar', $mantenimiento) }}"
                                      onsubmit="return confirm('{{ $mantenimiento->yaEmpezo() ? '¿Terminar el mantenimiento? Se podrá pedir desde hoy.' : '¿Quitar este mantenimiento programado?' }}');">
                                    @csrf
                                    @method('PATCH')
                                    <x-secondary-button type="submit" class="!px-4 !py-1.5 text-xs">{{ $mantenimiento->yaEmpezo() ? 'Terminar' : 'Quitar' }}</x-secondary-button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    <p class="mt-6"><a class="enlace" href="{{ route('equipos.index') }}">Volver al listado</a></p>
</x-modulo>
