{{-- Buscador del formulario de solicitud: busca por nombre, código, marca, modelo, ubicación, descripción o categoría. --}}
<form method="GET" action="{{ route('prestamos.create') }}" class="relative {{ $clase ?? '' }}" role="search">
    <input type="hidden" name="fecha" value="{{ $fecha->toDateString() }}">
    <input type="hidden" name="tipo" value="{{ $tipo }}">
    @if (! empty($conCategoria) && ($todas || $categoria))
        <input type="hidden" name="categoria" value="{{ $todas ? 'todas' : $categoria->id }}">
    @endif
    @if ($soloLibres)
        <input type="hidden" name="libres" value="1">
    @endif
    <x-icono nombre="buscar" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-marca-gris" />
    <input type="search" name="q" value="{{ $buscar }}" maxlength="80" aria-label="Buscar {{ $nombreTipo }}"
           placeholder="{{ $tipo === 'espacio' ? 'Busca por nombre, código o ubicación. Ej.: laboratorio' : 'Busca por nombre, marca o código. Ej.: adaptador' }}"
           class="block w-full !rounded-full py-3 pl-12 pr-28 campo-uts">
    {{-- Botón fijo: solo cambia de color (sin el movimiento de boton-principal, que lo descuadraba) --}}
    <button type="submit" class="absolute inset-y-1.5 right-1.5 inline-flex items-center gap-1.5 rounded-full bg-marca-oliva px-5 font-display text-sm font-bold text-white transition-colors hover:bg-marca-bosque active:bg-marca-noche focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/60">Buscar</button>
</form>
