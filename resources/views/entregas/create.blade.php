{{--
    Formulario de entrega. Siempre al solicitante, en persona. Todo se escribe en cada entrega.
    Si el préstamo anterior del mismo equipo no se ha recibido, no se puede entregar: se muestra quién
    lo tiene y la opción «No se pudo entregar» con su motivo.
--}}
@php
    use App\Models\Configuracion;
    use App\Models\Entrega;

    $solicitante = $prestamo->solicitante;
    $hora = fn ($fecha) => $fecha?->locale('es')->isoFormat('h:mm a');
    $esAprobada = $prestamo->estado === 'aprobado';
    $enHorario = $prestamo->enHorarioDeEntrega();
    $puedeEntregar = $enHorario && ! $sinDevolver;
    $maximoFotos = Configuracion::maximoFotos();
@endphp

<x-modulo titulo="Registrar entrega" :pendiente="false">
    @if ($errors->has('solicitud'))
        <p class="aviso-error"><x-icono nombre="alerta" class="h-5 w-5 shrink-0" />{{ $errors->first('solicitud') }}</p>
    @endif

    <a href="{{ route('entregas.index') }}" class="enlace inline-flex items-center gap-1 text-sm"><x-icono nombre="izquierda" class="h-4 w-4" />Entregas</a>

    {{-- ---------- La solicitud ---------- --}}
    @php($caja = 'rounded-2xl border border-marca-linea bg-marca-crema/60 px-4 py-3')
    <div class="mt-5 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
        <div class="{{ $caja }}">
            <p class="font-display text-xs font-bold uppercase tracking-wider text-marca-suave">Qué</p>
            <p class="mt-1 font-bold text-marca-noche">{{ $prestamo->equipo->nombre }}</p>
            <p class="text-xs text-marca-suave">{{ $prestamo->equipo->categoria->nombre }} · {{ $prestamo->equipo->codigo_inventario }}</p>
        </div>
        <div class="{{ $caja }}">
            <p class="font-display text-xs font-bold uppercase tracking-wider text-marca-suave">Quién lo recibe</p>
            <p class="mt-1 font-bold text-marca-noche">{{ $solicitante->name }}</p>
            <p class="text-xs text-marca-suave">{{ $solicitante->nombreTipoDocumento() }} {{ $solicitante->documento }} · {{ $solicitante->rol }}</p>
        </div>
        <div class="{{ $caja }}">
            <p class="font-display text-xs font-bold uppercase tracking-wider text-marca-suave">Día y bloque</p>
            <p class="mt-1 font-bold text-marca-noche">{{ ucfirst($prestamo->fechaLarga()) }}</p>
            <p class="text-marca-noche">{{ $prestamo->bloque() }}</p>
        </div>
        <div class="{{ $caja }}">
            <p class="font-display text-xs font-bold uppercase tracking-wider text-marca-suave">Devolver hasta</p>
            <p class="mt-1 font-bold text-marca-noche">{{ $hora($prestamo->limiteDevolucion()) }}</p>
            <p class="text-xs text-marca-suave">Fin del bloque + {{ Configuracion::duracion(Configuracion::minutosDevolucion()) }}</p>
        </div>
    </div>

    @if (! $esAprobada)
        <p class="mt-6 rounded-2xl border border-marca-linea px-4 py-3 text-sm text-marca-noche">
            Esta solicitud está <b>{{ mb_strtolower($prestamo->nombreEstado()) }}</b>: no hay nada que entregar.
            <a class="enlace" href="{{ route('prestamos.show', $prestamo) }}">Ver la solicitud</a>
        </p>
    @elseif (! $enHorario && now()->lessThan($prestamo->inicioEntrega()))
        <p class="mt-6 flex items-start gap-2 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-inset ring-amber-200">
            <x-icono nombre="reloj" class="mt-0.5 h-5 w-5 shrink-0" />
            Todavía no se puede entregar: se entrega desde las {{ $hora($prestamo->inicioEntrega()) }}{{ $prestamo->fecha->isToday() ? '' : ' del '.$prestamo->fecha->format('d/m/Y').'.' }}
        </p>
    @endif

    {{-- ---------- El préstamo anterior no se ha recibido ---------- --}}
    @if ($esAprobada && $sinDevolver)
        <section class="mt-6 rounded-3xl border-2 border-rose-300 bg-rose-50 p-5" role="alert">
            <h3 class="flex items-center gap-2 font-display text-base font-extrabold text-rose-900"><x-icono nombre="alerta" class="h-5 w-5" />Este equipo no se puede entregar</h3>
            <p class="mt-2 text-sm text-rose-900">
                No ha sido devuelto por <b>{{ $sinDevolver->solicitante->name }}</b> (préstamo del {{ $sinDevolver->fechaLarga() }}, de {{ $sinDevolver->bloque() }}{{ $sinDevolver->estado === 'por_corregir' ? ', con la devolución por corregir' : '' }}).
                Comunícate con esa persona para que lo devuelva lo más pronto posible:
                <a class="enlace font-bold" href="mailto:{{ $sinDevolver->solicitante->email }}">{{ $sinDevolver->solicitante->email }}</a>.
            </p>
            <p class="mt-2 text-sm text-rose-900">Si no llega a tiempo, marca abajo «No se pudo entregar» con el motivo.</p>
        </section>
    @endif

    @if ($puedeEntregar)
        {{-- ---------- Formulario de entrega ---------- --}}
        <form method="POST" action="{{ route('entregas.store', $prestamo) }}" enctype="multipart/form-data" class="mt-6 max-w-3xl space-y-5" novalidate
              x-data="{ estado: @js(old('estado', '')) }">
            @csrf

            <label class="flex items-start gap-3 rounded-2xl border-2 border-marca-lima bg-marca-limaclaro px-4 py-3 text-sm text-marca-noche">
                <input type="checkbox" name="verificado" value="1" @checked(old('verificado')) class="mt-0.5 h-5 w-5 rounded border-2 border-marca-linea text-marca-bosque focus:ring-marca-lima">
                <span><b>Verifiqué que quien recibe es {{ $solicitante->name }}</b> ({{ $solicitante->nombreTipoDocumento() }} {{ $solicitante->documento }}). Solo quien hizo la solicitud puede recogerlo, en persona.</span>
            </label>
            <x-input-error :messages="$errors->get('verificado')" class="-mt-3" />

            <fieldset>
                <legend class="font-display text-sm font-bold text-marca-noche">Estado en que se entrega<x-obligatorio /></legend>
                <div class="mt-2 flex flex-wrap gap-3">
                    @foreach (Entrega::ESTADOS as $valor => $etiqueta)
                        <label class="flex cursor-pointer items-center gap-2 rounded-full border-2 border-marca-linea px-4 py-2 text-sm font-semibold text-marca-noche transition has-[:checked]:border-marca-bosque has-[:checked]:bg-marca-limaclaro">
                            <input type="radio" name="estado" value="{{ $valor }}" x-model="estado" class="h-4 w-4 border-2 border-marca-linea text-marca-bosque focus:ring-marca-lima">{{ $etiqueta }}
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('estado')" class="mt-2" />
            </fieldset>

            <div>
                <x-input-label for="complementos">Complementos que se entregan<x-obligatorio /></x-input-label>
                <textarea id="complementos" name="complementos" rows="2" maxlength="2000" class="campo-uts mt-1 block w-full"
                          placeholder="Por ejemplo: tapa del lente, 2 patas, cable de poder · 80 sillas, control del aire">{{ old('complementos') }}</textarea>
                <x-input-error :messages="$errors->get('complementos')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="condiciones" value="Condiciones para devolverlo (opcional)" />
                <textarea id="condiciones" name="condiciones" rows="2" maxlength="2000" class="campo-uts mt-1 block w-full"
                          placeholder="Por ejemplo: entregar el salón barrido y el tablero limpio">{{ old('condiciones') }}</textarea>
                <x-input-error :messages="$errors->get('condiciones')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="observaciones">Observaciones <span x-show="estado !== 'con_observaciones'">(opcional)</span><span x-show="estado === 'con_observaciones'" x-cloak class="text-rose-600">*</span></x-input-label>
                <textarea id="observaciones" name="observaciones" rows="2" maxlength="2000" class="campo-uts mt-1 block w-full"
                          placeholder="Por ejemplo: tiene un pequeño rayón en la parte derecha; fuera de eso no tiene imperfecciones.">{{ old('observaciones') }}</textarea>
                <x-input-error :messages="$errors->get('observaciones')" class="mt-2" />
            </div>

            @if ($maximoFotos > 0)
                <div>
                    <x-input-label for="fotos" value="Fotos de cómo se entrega (opcional)" />
                    <input id="fotos" name="fotos[]" type="file" multiple accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                           class="mt-1 block w-full text-sm text-marca-noche file:mr-3 file:rounded-full file:border-0 file:bg-marca-limaclaro file:px-4 file:py-2 file:font-display file:text-sm file:font-bold file:text-marca-bosque hover:file:bg-marca-lima">
                    <p class="mt-1 text-xs text-marca-suave">Hasta {{ $maximoFotos }} {{ $maximoFotos === 1 ? 'foto' : 'fotos' }}, JPG, PNG o WebP de hasta 5 MB.</p>
                    <x-input-error :messages="$errors->get('fotos')" class="mt-2" />
                    @foreach ($errors->get('fotos.*') as $mensajes)
                        <x-input-error :messages="$mensajes" class="mt-1" />
                    @endforeach
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-3 border-t border-marca-linea pt-5">
                <x-primary-button><x-icono nombre="llave" class="h-4 w-4" />Registrar entrega</x-primary-button>
                <a class="enlace text-sm" href="{{ route('entregas.index') }}">Volver</a>
            </div>
        </form>
    @endif

    {{-- ---------- No se pudo entregar ---------- --}}
    @if ($esAprobada && now()->greaterThanOrEqualTo($prestamo->inicioEntrega()))
        <div class="mt-8 max-w-3xl border-t border-marca-linea pt-6" x-data="{ abierto: @js($errors->has('motivo_no_entrega') || (bool) $sinDevolver) }">
            <button type="button" class="enlace text-sm font-bold" x-show="! abierto" x-on:click="abierto = true">No se pudo entregar</button>
            <form method="POST" action="{{ route('entregas.no-entregado', $prestamo) }}" x-show="abierto" x-cloak>
                @csrf
                @method('PATCH')
                <label for="motivo_no_entrega" class="block font-display text-sm font-bold text-marca-noche">¿Por qué no se pudo entregar?<x-obligatorio /></label>
                <textarea id="motivo_no_entrega" name="motivo_no_entrega" rows="2" maxlength="500" class="campo-uts mt-1 block w-full"
                          placeholder="Por ejemplo: el préstamo anterior no se devolvió a tiempo.">{{ old('motivo_no_entrega') }}</textarea>
                <p class="mt-1 text-xs text-marca-suave">{{ $solicitante->name }} lo verá en su notificación. La solicitud queda como «No entregado».</p>
                <x-input-error :messages="$errors->get('motivo_no_entrega')" class="mt-2" />
                <div class="mt-3"><x-danger-button>Marcar como no entregado</x-danger-button></div>
            </form>
        </div>
    @endif
</x-modulo>
