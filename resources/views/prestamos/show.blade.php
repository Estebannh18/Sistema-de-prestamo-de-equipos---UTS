{{--
    Detalle de una solicitud. Al funcionario o al administrador, si está pendiente, le muestra las demás
    solicitudes del mismo espacio o equipo y bloque (quién y cuándo), y los botones para aprobar o rechazar.
    Si otra del mismo bloque ya está aprobada, primero hay que cancelarla (con su motivo) desde su detalle.
--}}
@php
    $equipo = $prestamo->equipo;
    $solicitante = $prestamo->solicitante;
    $cuando = fn ($fecha) => $fecha?->locale('es')->isoFormat('dddd D/MM/YYYY [a las] h:mm a');
    $puedeAtender = $esPersonal && $prestamo->estado === 'pendiente';
    $pendientesQueSeCruzan = $choques->where('estado', 'pendiente');
    $aprobadaQueSeCruza = $choques->firstWhere('estado', 'aprobado');
    $puedeCancelarAprobada = $esPersonal && $prestamo->estado === 'aprobado' && $prestamo->fin()?->isFuture();
@endphp

<x-modulo titulo="Solicitud" :pendiente="false">
    @if (session('status'))
        <p class="aviso-ok"><x-icono nombre="check" class="h-5 w-5 shrink-0" />{{ session('status') }}</p>
    @endif
    @if ($errors->has('solicitud'))
        <p class="aviso-error"><x-icono nombre="alerta" class="h-5 w-5 shrink-0" />{{ $errors->first('solicitud') }}</p>
    @endif

    <a href="{{ $esPersonal ? route('prestamos.pendientes', $volver) : route('prestamos.index') }}" class="enlace inline-flex items-center gap-1 text-sm">
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
        <span class="insignia insignia-{{ $prestamo->estado }} normal-case text-sm">{{ $prestamo->nombreEstado() }}</span>
    </header>

    @php $caja = 'rounded-2xl border border-marca-linea bg-marca-crema/60 px-4 py-3'; @endphp
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
                @if ($prestamo->cancelada_por === 'sistema')
                    <b>Se canceló automáticamente</b> el {{ $cuando($prestamo->cancelado_en) }} porque ya no está disponible.
                @elseif ($prestamo->cancelada_por === 'personal')
                    <b>La canceló</b> {{ $prestamo->canceladoPor?->name ?? 'la dependencia de préstamos' }} el {{ $cuando($prestamo->cancelado_en) }}.
                @else
                    El solicitante la canceló el {{ $cuando($prestamo->cancelado_en) }}.
                @endif
                @if ($prestamo->motivo_cancelacion)
                    <span class="mt-1 block"><b>Motivo:</b> {{ $prestamo->motivo_cancelacion }}</span>
                @endif
            @elseif ($prestamo->estado === 'no_recogido')
                El bloque terminó y no se recogió: no hubo entrega ni devolución.
            @elseif ($prestamo->estado === 'no_entregado')
                <b>No se pudo entregar.</b> Lo marcó {{ $prestamo->noEntregadoPor?->name ?? 'la dependencia de préstamos' }} el {{ $cuando($prestamo->no_entregado_en) }}.
                <span class="mt-1 block"><b>Motivo:</b> {{ $prestamo->motivo_no_entrega }}</span>
            @elseif ($prestamo->estado === 'entregado')
                <b>Entregado.</b> Se debe devolver hasta las {{ $prestamo->limiteDevolucion()->locale('es')->isoFormat('h:mm a') }}
                @if ($prestamo->estaAtrasado()) <span class="font-bold text-rose-700">· devolución atrasada {{ App\Models\Configuracion::duracion($prestamo->minutosDeRetraso()) }}</span>@endif
            @elseif (in_array($prestamo->estado, ['por_corregir', 'devuelto', 'devolucion_rechazada'], true))
                {{ ['por_corregir' => 'La devolución no se recibió todavía: tiene algo por corregir.', 'devuelto' => 'Devuelto.', 'devolucion_rechazada' => 'La devolución fue rechazada: el equipo quedó fuera de servicio y la solicitud quedó pendiente de gestión.'][$prestamo->estado] }}
                Detalle abajo.
            @endif
        </div>
    @endif

    {{-- ---------- Entrega y devolución ---------- --}}
    @if ($prestamo->entrega)
        @php $entrega = $prestamo->entrega; @endphp
        <section class="mt-6 grid gap-4 lg:grid-cols-2" aria-label="Entrega y devolución">
            <div class="rounded-3xl border border-marca-linea bg-marca-crema/60 p-5 text-sm">
                <h3 class="flex items-center gap-2 font-display text-base font-extrabold text-marca-noche"><x-icono nombre="llave" class="h-5 w-5 text-marca-oliva" />Entrega</h3>
                <p class="mt-1 text-xs text-marca-suave">{{ ucfirst($cuando($entrega->entregado_en)) }} · {{ $entrega->funcionario?->name ?? '—' }}</p>
                <dl class="mt-3 space-y-2">
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-marca-suave">Estado</dt><dd class="text-marca-noche">{{ $entrega->estadoLegible() }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-marca-suave">Complementos</dt><dd class="whitespace-pre-line text-marca-noche">{{ $entrega->complementos }}</dd></div>
                    @if ($entrega->condiciones)
                        <div><dt class="text-xs font-bold uppercase tracking-wider text-marca-suave">Condiciones para devolverlo</dt><dd class="whitespace-pre-line text-marca-noche">{{ $entrega->condiciones }}</dd></div>
                    @endif
                    @if ($entrega->observaciones)
                        <div><dt class="text-xs font-bold uppercase tracking-wider text-marca-suave">Observaciones</dt><dd class="whitespace-pre-line text-marca-noche">{{ $entrega->observaciones }}</dd></div>
                    @endif
                </dl>
                @include('prestamos.partes.fotos', ['fotos' => $entrega->fotos])
            </div>

            <div class="space-y-3">
                @forelse ($prestamo->devoluciones as $revision)
                    @php
                        $color = ['aceptada' => 'border-emerald-300 bg-emerald-50', 'por_corregir' => 'border-amber-300 bg-amber-50', 'rechazada' => 'border-rose-300 bg-rose-50'][$revision->resultado];
                    @endphp
                    <div class="rounded-3xl border p-5 text-sm {{ $color }}">
                        <h3 class="flex items-center gap-2 font-display text-base font-extrabold text-marca-noche">
                            <x-icono nombre="devolucion" class="h-5 w-5 text-marca-oliva" />
                            Devolución{{ $prestamo->devoluciones->count() > 1 ? ' · revisión '.$loop->iteration : '' }}: {{ $revision->resultadoLegible() }}
                        </h3>
                        <p class="mt-1 text-xs text-marca-suave">
                            {{ ucfirst($cuando($revision->recibido_en)) }} · {{ $revision->funcionario?->name ?? '—' }}
                            @if ($revision->conRetraso()) · <b class="text-rose-700">con {{ App\Models\Configuracion::duracion($revision->minutos_retraso) }} de retraso</b>@endif
                        </p>
                        <dl class="mt-3 space-y-2">
                            <div><dt class="text-xs font-bold uppercase tracking-wider text-marca-suave">Estado</dt><dd class="text-marca-noche">{{ $revision->estadoLegible() }}</dd></div>
                            <div><dt class="text-xs font-bold uppercase tracking-wider text-marca-suave">Complementos</dt><dd class="whitespace-pre-line text-marca-noche">{{ $revision->complementos }}</dd></div>
                            @unless ($revision->complementos_completos)
                                <div><dt class="text-xs font-bold uppercase tracking-wider text-marca-suave">Faltan</dt><dd class="whitespace-pre-line text-marca-noche">{{ $revision->faltantes }}</dd></div>
                            @endunless
                            @if ($revision->condiciones_cumplidas !== null)
                                <div><dt class="text-xs font-bold uppercase tracking-wider text-marca-suave">¿Cumplió las condiciones?</dt><dd class="text-marca-noche">{{ $revision->condiciones_cumplidas ? 'Sí' : 'No' }}</dd></div>
                            @endif
                            @if ($revision->observaciones)
                                <div><dt class="text-xs font-bold uppercase tracking-wider text-marca-suave">Observaciones</dt><dd class="whitespace-pre-line text-marca-noche">{{ $revision->observaciones }}</dd></div>
                            @endif
                            @if ($revision->motivo)
                                <div><dt class="text-xs font-bold uppercase tracking-wider text-marca-suave">Motivo</dt><dd class="text-marca-noche">{{ $revision->motivo }}</dd></div>
                            @endif
                            @if ($revision->plazo)
                                <div><dt class="text-xs font-bold uppercase tracking-wider text-marca-suave">Plazo</dt><dd class="text-marca-noche">{{ $revision->plazo }}</dd></div>
                            @endif
                            @if ($revision->pendiente)
                                <div>
                                    <dt class="text-xs font-bold uppercase tracking-wider text-marca-suave">Pendiente</dt>
                                    <dd class="text-marca-noche">{{ $revision->pendiente }}
                                        @if ($revision->pendiente_resuelto_en)
                                            <span class="block text-xs font-bold text-emerald-700">Resuelto el {{ $cuando($revision->pendiente_resuelto_en) }}{{ $revision->pendienteResueltoPor ? ' por '.$revision->pendienteResueltoPor->name : '' }}.</span>
                                        @else
                                            <span class="block text-xs font-bold text-amber-800">Sin resolver.</span>
                                        @endif
                                    </dd>
                                </div>
                            @endif
                        </dl>
                        @include('prestamos.partes.fotos', ['fotos' => $revision->fotos])
                        @if ($esPersonal && $revision->tienePendienteSinResolver())
                            <form method="POST" action="{{ route('devoluciones.resolver', $revision) }}" class="mt-3">
                                @csrf
                                @method('PATCH')
                                <x-secondary-button type="submit" class="!px-4 !py-1.5 text-xs"><x-icono nombre="check" class="h-4 w-4" />Marcar como resuelto</x-secondary-button>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="rounded-3xl border-2 border-dashed border-marca-linea p-5 text-sm text-marca-suave">Todavía no se ha registrado la devolución.</div>
                @endforelse

                @if ($esPersonal && in_array($prestamo->estado, ['entregado', 'por_corregir'], true))
                    <a href="{{ route('devoluciones.create', $prestamo) }}" class="boton-principal text-sm"><x-icono nombre="devolucion" class="h-4 w-4" />Registrar devolución</a>
                @endif
            </div>
        </section>
    @elseif ($esPersonal && $prestamo->enHorarioDeEntrega())
        <p class="mt-6"><a href="{{ route('entregas.create', $prestamo) }}" class="boton-principal text-sm"><x-icono nombre="llave" class="h-4 w-4" />Registrar entrega</a></p>
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
                                <a class="enlace text-xs" href="{{ route('prestamos.show', array_filter([$otra->id, 'desde' => $prestamo->id, 'volver' => http_build_query($volver)])) }}">{{ $otra->estado === 'aprobado' ? 'Abrir para cancelarla' : 'Ver' }}</a>
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
        @if ($aprobadaQueSeCruza)
            <p class="mt-6 flex items-start gap-2 rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-900 ring-1 ring-inset ring-rose-200">
                <x-icono nombre="alerta" class="mt-0.5 h-5 w-5 shrink-0" />
                <span>
                    Para aprobar esta solicitud, primero debes cancelar la solicitud aprobada de <b>{{ $aprobadaQueSeCruza->solicitante->name }}</b>
                    ({{ $aprobadaQueSeCruza->solicitante->rol }}) para este mismo bloque.
                    <a class="enlace font-bold" href="{{ route('prestamos.show', array_filter([$aprobadaQueSeCruza->id, 'desde' => $prestamo->id, 'volver' => http_build_query($volver)])) }}">Abrirla para cancelarla</a>.
                    También puedes rechazar esta.
                </span>
            </p>
        @endif
        <div class="mt-6 flex flex-wrap items-start gap-4 border-t border-marca-linea pt-6" x-data="{ rechazar: @js($errors->has('motivo')) }">
            <form method="POST" action="{{ route('prestamos.aprobar', $prestamo) }}" x-show="! rechazar"
                  @if ($pendientesQueSeCruzan->isNotEmpty()) onsubmit="return confirm('Al aprobar esta solicitud se rechazarán automáticamente las otras pendientes del mismo bloque. ¿Continuar?');" @endif>
                @csrf
                @method('PATCH')
                <input type="hidden" name="volver" value="{{ http_build_query($volver) }}">
                <x-primary-button :disabled="(bool) $aprobadaQueSeCruza" class="disabled:cursor-not-allowed disabled:opacity-50"><x-icono nombre="check" class="h-4 w-4" />Aprobar</x-primary-button>
            </form>
            <x-danger-button type="button" x-show="! rechazar" x-on:click="rechazar = true; $nextTick(() => $refs.motivo.focus())"><x-icono nombre="cerrar" class="h-4 w-4" />Rechazar</x-danger-button>

            <form method="POST" action="{{ route('prestamos.rechazar', $prestamo) }}" class="w-full max-w-2xl" x-show="rechazar" x-cloak>
                @csrf
                @method('PATCH')
                <input type="hidden" name="volver" value="{{ http_build_query($volver) }}">
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
    @elseif ($puedeCancelarAprobada)
        {{-- ---------- El personal cancela una aprobada (por ejemplo, para aprobar otra del mismo bloque) ---------- --}}
        <div class="mt-6 border-t border-marca-linea pt-6" x-data="{ abierto: @js($errors->has('motivo_cancelacion') || $desde) }">
            <x-danger-button type="button" x-show="! abierto" x-on:click="abierto = true; $nextTick(() => $refs.motivoCancelacion.focus())"><x-icono nombre="cerrar" class="h-4 w-4" />Cancelar esta solicitud</x-danger-button>
            <form method="POST" action="{{ route('prestamos.cancelar-aprobada', $prestamo) }}" class="w-full max-w-2xl" x-show="abierto" x-cloak>
                @csrf
                @method('PATCH')
                <input type="hidden" name="volver" value="{{ http_build_query($volver) }}">
                @if ($desde)<input type="hidden" name="desde" value="{{ $desde }}">@endif
                <label for="motivo_cancelacion" class="block font-display text-sm font-bold text-marca-noche">Motivo de la cancelación<x-obligatorio /></label>
                <textarea id="motivo_cancelacion" name="motivo_cancelacion" rows="3" x-ref="motivoCancelacion" maxlength="500" required class="campo-uts mt-1 block w-full"
                          placeholder="Por ejemplo: este salón ya estaba asignado para una actividad; solicita otro espacio.">{{ old('motivo_cancelacion') }}</textarea>
                <p class="mt-1 text-xs text-marca-suave">{{ $solicitante->name }} lo verá en su notificación. El bloque queda libre.</p>
                <x-input-error :messages="$errors->get('motivo_cancelacion')" class="mt-2" />
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <x-danger-button>Cancelar solicitud</x-danger-button>
                    <button type="button" class="enlace text-sm" x-on:click="abierto = false">Volver</button>
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
