{{--
    Campana de notificaciones de la barra superior. Muestra cuántas hay sin leer y las más recientes,
    y se actualiza sola cada 30 segundos (sin recargar la página).
    El administrador no recibe avisos, así que para él no se muestra.
--}}
@if (Auth::check() && ! Auth::user()->esAdministrador())
    @php($datos = App\Http\Controllers\NotificacionController::datosCampana(Auth::user()))
    <div class="relative" x-data="{
            abierta: false,
            total: @js($datos['total']),
            items: @js($datos['items']),
            async actualizar() {
                try {
                    const r = await fetch(@js(route('notificaciones.resumen')), { headers: { Accept: 'application/json' } });
                    if (r.ok) { const d = await r.json(); this.total = d.total; this.items = d.items; }
                } catch (e) {}
            },
         }"
         x-init="setInterval(() => actualizar(), 30000)"
         x-on:keydown.escape.window="abierta = false"
         x-on:click.outside="abierta = false">
        <button type="button" x-on:click="abierta = ! abierta" :aria-expanded="abierta"
                class="relative flex h-11 w-11 items-center justify-center rounded-full border-2 border-marca-linea bg-white text-marca-bosque transition hover:border-marca-lima focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/60"
                :aria-label="total > 0 ? 'Notificaciones: ' + total + ' sin leer' : 'Notificaciones'">
            <x-icono nombre="campana" class="h-5 w-5" />
            <span x-show="total > 0" x-cloak x-text="total > 99 ? '99+' : total"
                  class="absolute -right-1 -top-1 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-rose-600 px-1 font-display text-[11px] font-extrabold leading-none text-white ring-2 ring-white"></span>
        </button>

        <div x-show="abierta" x-cloak x-transition.origin.top.right
             class="absolute right-0 z-50 mt-2 w-[22rem] max-w-[calc(100vw-2rem)] overflow-hidden rounded-3xl border border-marca-linea bg-white shadow-[0_20px_40px_-18px_rgba(11,61,42,0.55)]">
            <div class="franja-uts h-1.5" aria-hidden="true"></div>
            <div class="flex items-center justify-between gap-3 border-b border-marca-linea px-4 py-3">
                <p class="font-display text-sm font-extrabold text-marca-noche">Notificaciones</p>
                <form method="POST" action="{{ route('notificaciones.leer-todas') }}" x-show="total > 0">
                    @csrf
                    <button type="submit" class="enlace text-xs">Marcar todas como leídas</button>
                </form>
            </div>

            <ul class="max-h-96 divide-y divide-marca-linea overflow-y-auto">
                <template x-for="item in items" :key="item.id">
                    <li class="px-4 py-3">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                                  :class="['rechazada', 'cancelada', 'no_entregado', 'devolucion_rechazada'].includes(item.tipo) ? 'bg-rose-100 text-rose-700' : (['aprobada', 'devolucion_aceptada'].includes(item.tipo) ? 'bg-emerald-100 text-emerald-700' : (item.tipo === 'por_corregir' ? 'bg-amber-100 text-amber-700' : 'bg-marca-limaclaro text-marca-oliva'))">
                                <x-icono nombre="campana" class="h-4 w-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="font-display text-sm font-bold text-marca-noche" x-text="item.titulo"></p>
                                <p class="mt-0.5 text-xs text-marca-noche/80" x-text="item.mensaje"></p>
                                <div class="mt-2 flex items-center justify-between gap-2">
                                    <span class="text-[11px] text-marca-suave" x-text="item.hace"></span>
                                    <form method="POST" :action="item.abrir">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-1 rounded-full bg-marca-oliva px-3 py-1 font-display text-[11px] font-bold text-white transition hover:bg-marca-bosque focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/60">
                                            <span x-text="item.tipo === 'por_aprobar' ? 'Ir a la solicitud' : 'Ver mi solicitud'"></span>
                                            <x-icono nombre="derecha" class="h-3.5 w-3.5" />
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </li>
                </template>
            </ul>
            <p x-show="items.length === 0" class="px-4 py-8 text-center text-sm text-marca-suave">No tienes notificaciones nuevas.</p>

            <a href="{{ route('notificaciones.index') }}" class="block border-t border-marca-linea bg-marca-crema/60 px-4 py-2.5 text-center font-display text-xs font-bold text-marca-oliva transition hover:bg-marca-limaclaro">Ver todas las notificaciones</a>
        </div>
    </div>
@endif
