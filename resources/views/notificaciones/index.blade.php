{{-- Bandeja de notificaciones: las más recientes primero; las no leídas resaltadas. --}}
<x-modulo titulo="Notificaciones" :pendiente="false">
    @if (session('status'))
        <p class="aviso-ok"><x-icono nombre="check" class="h-5 w-5 shrink-0" />{{ session('status') }}</p>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-marca-suave">Avisos de tus solicitudes y, si eres funcionario, de las solicitudes por aprobar.</p>
        @if (Auth::user()->unreadNotifications()->exists())
            <form method="POST" action="{{ route('notificaciones.leer-todas') }}">
                @csrf
                <x-secondary-button type="submit">Marcar todas como leídas</x-secondary-button>
            </form>
        @endif
    </div>

    @if ($notificaciones->isEmpty())
        <div class="mt-6 rounded-3xl border-2 border-dashed border-marca-linea px-6 py-12 text-center">
            <x-icono nombre="campana" class="mx-auto h-8 w-8 text-marca-gris" />
            <p class="mt-3 font-display font-bold text-marca-noche">No tienes notificaciones.</p>
        </div>
    @else
        <ul class="mt-6 space-y-3">
            @foreach ($notificaciones as $notificacion)
                @php($tipo = $notificacion->data['type'] ?? '')
                <li class="flex flex-wrap items-start gap-4 rounded-3xl border p-4 {{ $notificacion->read_at ? 'border-marca-linea bg-white' : 'border-marca-lima bg-marca-limaclaro/50' }}">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ match (true) { in_array($tipo, ['rechazada', 'cancelada', 'no_entregado', 'devolucion_rechazada'], true) => 'bg-rose-100 text-rose-700', in_array($tipo, ['aprobada', 'devolucion_aceptada'], true) => 'bg-emerald-100 text-emerald-700', $tipo === 'por_corregir' => 'bg-amber-100 text-amber-700', default => 'bg-marca-limaclaro text-marca-oliva' } }}">
                        <x-icono nombre="campana" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2 font-display text-sm font-extrabold text-marca-noche">
                            {{ $notificacion->data['title'] ?? 'Notificación' }}
                            @unless ($notificacion->read_at)
                                <span class="rounded-full bg-rose-600 px-2 py-0.5 text-[10px] font-extrabold uppercase text-white">Nueva</span>
                            @endunless
                        </p>
                        <p class="mt-1 text-sm text-marca-noche/80">{{ $notificacion->data['message'] ?? '' }}</p>
                        <p class="mt-1 text-xs text-marca-suave">{{ $notificacion->created_at->locale('es')->diffForHumans() }}</p>
                    </div>
                    <form method="POST" action="{{ route('notificaciones.abrir', $notificacion->id) }}" class="self-center">
                        @csrf
                        <button type="submit" class="boton-principal !px-4 !py-1.5 text-xs">{{ $tipo === 'por_aprobar' ? 'Ir a la solicitud' : 'Ver mi solicitud' }}</button>
                    </form>
                </li>
            @endforeach
        </ul>

        @if ($notificaciones->hasPages())
            <div class="mt-6">{{ $notificaciones->links() }}</div>
        @endif
    @endif
</x-modulo>
