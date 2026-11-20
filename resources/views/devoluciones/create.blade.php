{{--
    Formulario de una revisión de la devolución. A un lado, lo que se anotó en la entrega y las
    revisiones anteriores, para comparar. Todo se escribe a mano. Si se rechaza, se avisa cuántas
    solicitudes posteriores se cancelarán.
--}}
@php
    use App\Models\Configuracion;
    use App\Models\Devolucion;

    $entrega = $prestamo->entrega;
    $hora = fn ($fecha) => $fecha?->locale('es')->isoFormat('h:mm a');
    $pendienteDeRecibir = in_array($prestamo->estado, ['entregado', 'por_corregir'], true);
    $retraso = $prestamo->minutosDeRetraso();
    $maximoFotos = Configuracion::maximoFotos();
    $confirmarRechazo = $posteriores->isEmpty()
        ? '¿Rechazar la devolución? El equipo quedará fuera de servicio.'
        : '¿Rechazar la devolución? El equipo quedará fuera de servicio y se cancelarán '.$posteriores->count().' '.($posteriores->count() === 1 ? 'solicitud posterior' : 'solicitudes posteriores').' con aviso a cada solicitante.';
@endphp

<x-modulo titulo="Registrar devolución" :pendiente="false">
    @if ($errors->has('solicitud'))
        <p class="aviso-error"><x-icono nombre="alerta" class="h-5 w-5 shrink-0" />{{ $errors->first('solicitud') }}</p>
    @endif

    <a href="{{ route('devoluciones.index') }}" class="enlace inline-flex items-center gap-1 text-sm"><x-icono nombre="izquierda" class="h-4 w-4" />Devoluciones</a>

    <header class="mt-5 flex flex-wrap items-start justify-between gap-4 border-b border-marca-linea pb-5">
        <div class="min-w-0">
            <h2 class="font-display text-2xl font-extrabold leading-tight text-marca-noche">{{ $prestamo->equipo->nombre }}</h2>
            <p class="text-sm text-marca-suave">{{ $prestamo->equipo->codigo_inventario }} · {{ $prestamo->solicitante->name }} ({{ $prestamo->solicitante->rol }}) · {{ ucfirst($prestamo->fechaLarga()) }}, {{ $prestamo->bloque() }}</p>
        </div>
        <div class="text-right text-sm">
            <span class="insignia insignia-{{ $prestamo->estado }} normal-case">{{ $prestamo->nombreEstado() }}</span>
            <p class="mt-1 text-marca-suave">Devolver hasta las {{ $hora($prestamo->limiteDevolucion()) }}</p>
            @if ($pendienteDeRecibir && $retraso > 0)
                <p class="font-bold text-rose-700">Con {{ Configuracion::duracion($retraso) }} de retraso</p>
            @endif
        </div>
    </header>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        {{-- ---------- Formulario ---------- --}}
        <div class="min-w-0">
            @unless ($pendienteDeRecibir)
                <p class="rounded-2xl border border-marca-linea px-4 py-3 text-sm text-marca-noche">
                    Esta solicitud está <b>{{ mb_strtolower($prestamo->nombreEstado()) }}</b>: no está pendiente de devolución.
                    <a class="enlace" href="{{ route('prestamos.show', $prestamo) }}">Ver la solicitud</a>
                </p>
            @else
                <form method="POST" action="{{ route('devoluciones.store', $prestamo) }}" enctype="multipart/form-data" class="space-y-5" novalidate
                      x-data="{ resultado: @js(old('resultado', '')), completos: @js(old('complementos_completos', '')), conPendiente: @js((bool) old('con_pendiente')) }"
                      x-on:submit="if (resultado === 'rechazada' && ! confirm(@js($confirmarRechazo))) $event.preventDefault()">
                    @csrf

                    <fieldset>
                        <legend class="font-display text-sm font-bold text-marca-noche">Estado en que se recibe<x-obligatorio /></legend>
                        <div class="mt-2 flex flex-wrap gap-3">
                            @foreach (Devolucion::ESTADOS as $valor => $etiqueta)
                                <label class="flex cursor-pointer items-center gap-2 rounded-full border-2 border-marca-linea px-4 py-2 text-sm font-semibold text-marca-noche transition has-[:checked]:border-marca-bosque has-[:checked]:bg-marca-limaclaro">
                                    <input type="radio" name="estado" value="{{ $valor }}" @checked(old('estado') === $valor) class="h-4 w-4 border-2 border-marca-linea text-marca-bosque focus:ring-marca-lima">{{ $etiqueta }}
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('estado')" class="mt-2" />
                    </fieldset>

                    <div>
                        <x-input-label for="complementos">Complementos que devuelve<x-obligatorio /></x-input-label>
                        <textarea id="complementos" name="complementos" rows="2" maxlength="2000" class="campo-uts mt-1 block w-full"
                                  placeholder="Escríbelos como los recibes: tapa del lente, 1 pata, cable de poder · 79 sillas">{{ old('complementos') }}</textarea>
                        <x-input-error :messages="$errors->get('complementos')" class="mt-2" />
                    </div>

                    <fieldset>
                        <legend class="font-display text-sm font-bold text-marca-noche">¿Devolvió todos los complementos?<x-obligatorio /></legend>
                        <div class="mt-2 flex gap-3">
                            @foreach (['1' => 'Sí', '0' => 'No'] as $valor => $etiqueta)
                                <label class="flex cursor-pointer items-center gap-2 rounded-full border-2 border-marca-linea px-4 py-2 text-sm font-semibold text-marca-noche transition has-[:checked]:border-marca-bosque has-[:checked]:bg-marca-limaclaro">
                                    <input type="radio" name="complementos_completos" value="{{ $valor }}" x-model="completos" class="h-4 w-4 border-2 border-marca-linea text-marca-bosque focus:ring-marca-lima">{{ $etiqueta }}
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('complementos_completos')" class="mt-2" />
                        <div class="mt-3" x-show="completos === '0'" x-cloak>
                            <x-input-label for="faltantes">¿Qué falta?<x-obligatorio /></x-input-label>
                            <textarea id="faltantes" name="faltantes" rows="2" maxlength="2000" class="campo-uts mt-1 block w-full"
                                      placeholder="Por ejemplo: se entregaron 80 sillas y se devolvieron 79.">{{ old('faltantes') }}</textarea>
                        </div>
                        <x-input-error :messages="$errors->get('faltantes')" class="mt-2" />
                    </fieldset>

                    @if (filled($entrega?->condiciones))
                        <fieldset>
                            <legend class="font-display text-sm font-bold text-marca-noche">¿Cumplió las condiciones?<x-obligatorio /></legend>
                            <p class="text-xs text-marca-suave">«{{ $entrega->condiciones }}»</p>
                            <div class="mt-2 flex gap-3">
                                @foreach (['1' => 'Sí', '0' => 'No'] as $valor => $etiqueta)
                                    <label class="flex cursor-pointer items-center gap-2 rounded-full border-2 border-marca-linea px-4 py-2 text-sm font-semibold text-marca-noche transition has-[:checked]:border-marca-bosque has-[:checked]:bg-marca-limaclaro">
                                        <input type="radio" name="condiciones_cumplidas" value="{{ $valor }}" @checked(old('condiciones_cumplidas') === $valor) class="h-4 w-4 border-2 border-marca-linea text-marca-bosque focus:ring-marca-lima">{{ $etiqueta }}
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('condiciones_cumplidas')" class="mt-2" />
                        </fieldset>
                    @endif

                    <div>
                        <x-input-label for="observaciones" value="Observaciones (opcional)" />
                        <textarea id="observaciones" name="observaciones" rows="2" maxlength="2000" class="campo-uts mt-1 block w-full">{{ old('observaciones') }}</textarea>
                        <x-input-error :messages="$errors->get('observaciones')" class="mt-2" />
                    </div>

                    @if ($maximoFotos > 0)
                        <div>
                            <x-input-label for="fotos" value="Fotos de cómo se recibe (opcional)" />
                            <input id="fotos" name="fotos[]" type="file" multiple accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                   class="mt-1 block w-full text-sm text-marca-noche file:mr-3 file:rounded-full file:border-0 file:bg-marca-limaclaro file:px-4 file:py-2 file:font-display file:text-sm file:font-bold file:text-marca-bosque hover:file:bg-marca-lima">
                            <p class="mt-1 text-xs text-marca-suave">Hasta {{ $maximoFotos }} {{ $maximoFotos === 1 ? 'foto' : 'fotos' }}, JPG, PNG o WebP de hasta 5 MB.</p>
                            <x-input-error :messages="$errors->get('fotos')" class="mt-2" />
                            @foreach ($errors->get('fotos.*') as $mensajes)
                                <x-input-error :messages="$mensajes" class="mt-1" />
                            @endforeach
                        </div>
                    @endif

                    {{-- ---------- Resultado ---------- --}}
                    <fieldset class="rounded-3xl border-2 border-marca-linea p-4">
                        <legend class="px-2 font-display text-base font-extrabold text-marca-noche">Resultado<x-obligatorio /></legend>
                        <div class="grid gap-3 sm:grid-cols-3">
                            @foreach ([
                                'aceptada' => ['Aceptada', 'Se recibe. Puede quedar un pendiente.', 'has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50'],
                                'por_corregir' => ['Por corregir', 'No se recibe todavía: se da la oportunidad de arreglarlo.', 'has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50'],
                                'rechazada' => ['Rechazada', 'Llegó dañado: queda fuera de servicio.', 'has-[:checked]:border-rose-500 has-[:checked]:bg-rose-50'],
                            ] as $valor => [$titulo, $ayuda, $color])
                                <label class="flex cursor-pointer flex-col gap-1 rounded-2xl border-2 border-marca-linea p-3 text-sm transition {{ $color }}">
                                    <span class="flex items-center gap-2 font-display font-bold text-marca-noche">
                                        <input type="radio" name="resultado" value="{{ $valor }}" x-model="resultado" class="h-4 w-4 border-2 border-marca-linea text-marca-bosque focus:ring-marca-lima">{{ $titulo }}
                                    </span>
                                    <span class="text-xs text-marca-suave">{{ $ayuda }}</span>
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('resultado')" class="mt-2" />

                        <div class="mt-4 space-y-4">
                            <div x-show="resultado === 'aceptada'" x-cloak>
                                <label class="flex items-center gap-2 text-sm font-semibold text-marca-noche">
                                    <input type="checkbox" name="con_pendiente" value="1" x-model="conPendiente" class="h-4 w-4 rounded border-2 border-marca-linea text-marca-bosque focus:ring-marca-lima">Queda un pendiente
                                </label>
                                <div class="mt-2" x-show="conPendiente" x-cloak>
                                    <textarea id="pendiente" name="pendiente" rows="2" maxlength="500" class="campo-uts block w-full"
                                              placeholder="Por ejemplo: falta una pata del video beam; la traerá mañana.">{{ old('pendiente') }}</textarea>
                                </div>
                                <x-input-error :messages="$errors->get('pendiente')" class="mt-2" />
                            </div>

                            <div x-show="resultado === 'por_corregir' || resultado === 'rechazada'" x-cloak>
                                <x-input-label for="motivo">Motivo<x-obligatorio /></x-input-label>
                                <textarea id="motivo" name="motivo" rows="2" maxlength="500" class="campo-uts mt-1 block w-full"
                                          x-bind:placeholder="resultado === 'rechazada' ? 'Por ejemplo: el video beam no enciende y tiene la carcasa rota.' : 'Por ejemplo: el piso estaba sucio y el tablero sin limpiar.'">{{ old('motivo') }}</textarea>
                                <p class="mt-1 text-xs text-marca-suave">{{ $prestamo->solicitante->name }} lo verá en su notificación.</p>
                                <x-input-error :messages="$errors->get('motivo')" class="mt-2" />
                            </div>

                            <div x-show="resultado === 'por_corregir'" x-cloak>
                                <x-input-label for="plazo" value="Plazo para corregirlo (opcional)" />
                                <input id="plazo" name="plazo" type="text" maxlength="100" value="{{ old('plazo') }}" class="campo-uts mt-1 block w-full sm:w-72" placeholder="Por ejemplo: 5 horas">
                                <p class="mt-1 text-xs text-marca-suave">Es una observación: el sistema no lo cuenta.</p>
                                <x-input-error :messages="$errors->get('plazo')" class="mt-2" />
                            </div>

                            <p x-show="resultado === 'rechazada'" x-cloak class="flex items-start gap-2 rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-900 ring-1 ring-inset ring-rose-200">
                                <x-icono nombre="alerta" class="mt-0.5 h-5 w-5 shrink-0" />
                                <span>
                                    «{{ $prestamo->equipo->nombre }}» quedará <b>fuera de servicio</b> hasta que alguien termine su mantenimiento.
                                    @if ($posteriores->isNotEmpty())
                                        Se cancelarán <b>{{ $posteriores->count() }} {{ $posteriores->count() === 1 ? 'solicitud posterior' : 'solicitudes posteriores' }}</b>
                                        ({{ $posteriores->map(fn ($p) => $p->solicitante->name.', '.$p->fechaLarga().' '.$p->bloque())->join('; ') }}) y se le avisará a cada solicitante.
                                    @else
                                        No tiene solicitudes posteriores.
                                    @endif
                                </span>
                            </p>
                        </div>
                    </fieldset>

                    <div class="flex flex-wrap items-center gap-3">
                        <x-primary-button><x-icono nombre="devolucion" class="h-4 w-4" />Registrar devolución</x-primary-button>
                        <a class="enlace text-sm" href="{{ route('devoluciones.index') }}">Volver</a>
                    </div>
                </form>
            @endunless
        </div>

        {{-- ---------- Lo que se anotó en la entrega y las revisiones anteriores ---------- --}}
        <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start">
            <section class="rounded-3xl border border-marca-linea bg-marca-crema/60 p-5 text-sm">
                <h3 class="flex items-center gap-2 font-display text-base font-extrabold text-marca-noche"><x-icono nombre="llave" class="h-5 w-5 text-marca-oliva" />Así se entregó</h3>
                @if ($entrega)
                    <p class="mt-1 text-xs text-marca-suave">{{ $entrega->entregado_en->format('d/m/Y \a \l\a\s g:i a') }} · {{ $entrega->funcionario?->name ?? '—' }}</p>
                    <dl class="mt-3 space-y-2">
                        <div><dt class="text-xs font-bold uppercase tracking-wider text-marca-suave">Estado</dt><dd class="text-marca-noche">{{ $entrega->estadoLegible() }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase tracking-wider text-marca-suave">Complementos</dt><dd class="whitespace-pre-line text-marca-noche">{{ $entrega->complementos }}</dd></div>
                        @if ($entrega->condiciones)
                            <div><dt class="text-xs font-bold uppercase tracking-wider text-marca-suave">Condiciones</dt><dd class="whitespace-pre-line text-marca-noche">{{ $entrega->condiciones }}</dd></div>
                        @endif
                        @if ($entrega->observaciones)
                            <div><dt class="text-xs font-bold uppercase tracking-wider text-marca-suave">Observaciones</dt><dd class="whitespace-pre-line text-marca-noche">{{ $entrega->observaciones }}</dd></div>
                        @endif
                    </dl>
                    @include('prestamos.partes.fotos', ['fotos' => $entrega->fotos])
                @else
                    <p class="mt-2 text-marca-suave">No hay datos de la entrega.</p>
                @endif
            </section>

            @foreach ($prestamo->devoluciones as $revision)
                <section class="rounded-3xl border border-amber-300 bg-amber-50 p-5 text-sm">
                    <h3 class="font-display text-sm font-extrabold text-amber-900">Revisión {{ $loop->iteration }}: {{ $revision->resultadoLegible() }}</h3>
                    <p class="text-xs text-amber-900/80">{{ $revision->recibido_en->format('d/m/Y \a \l\a\s g:i a') }} · {{ $revision->funcionario?->name ?? '—' }}</p>
                    <p class="mt-2 text-amber-900"><b>Motivo:</b> {{ $revision->motivo }}@if ($revision->plazo) · <b>Plazo:</b> {{ $revision->plazo }}@endif</p>
                    @include('prestamos.partes.fotos', ['fotos' => $revision->fotos])
                </section>
            @endforeach
        </aside>
    </div>
</x-modulo>
