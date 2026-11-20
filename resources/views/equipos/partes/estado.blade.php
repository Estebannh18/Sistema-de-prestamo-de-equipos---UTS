{{-- Estado del equipo o espacio. No se elige aquí: lo ponen el mantenimiento programado, la entrega y la devolución. --}}
<div class="{{ $clase ?? '' }}">
    <x-input-label value="Estado" />
    @if ($equipo)
        <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-marca-suave">
            <span class="insignia insignia-{{ $equipo->estado }}">{{ $equipo->estado }}</span>
            Para ponerlo en mantenimiento, prográmalo desde su detalle.
        </p>
    @else
        <p class="mt-1 text-sm text-marca-suave">Queda disponible. Para ponerlo en mantenimiento, prográmalo desde su detalle después de crearlo.</p>
    @endif
</div>
