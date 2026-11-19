{{--
    Paginación con el diseño UTS y en español. Reemplaza la vista de Laravel (php artisan vendor:publish --tag=laravel-pagination);
    la usan todas las listas paginadas: solicitudes, por aprobar, notificaciones y resultados del formulario de solicitud.
--}}
@php
    $boton = 'inline-flex h-10 min-w-10 items-center justify-center rounded-full px-3 font-display text-sm font-bold transition focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/60';
    $activo = 'bg-white text-marca-bosque ring-1 ring-inset ring-marca-linea hover:ring-marca-lima hover:text-marca-noche';
    $inactivo = 'cursor-not-allowed bg-marca-crema text-marca-gris ring-1 ring-inset ring-marca-linea';
@endphp

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Paginación" class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-marca-suave">
            @if ($paginator->firstItem())
                Mostrando <b class="text-marca-noche">{{ $paginator->firstItem() }}</b> a <b class="text-marca-noche">{{ $paginator->lastItem() }}</b>
                de <b class="text-marca-noche">{{ $paginator->total() }}</b>
            @else
                {{ $paginator->count() }} de {{ $paginator->total() }}
            @endif
        </p>

        <div class="flex flex-wrap items-center gap-1.5">
            {{-- Anterior --}}
            @if ($paginator->onFirstPage())
                <span class="{{ $boton }} {{ $inactivo }}" aria-disabled="true" aria-label="Anterior"><x-icono nombre="izquierda" class="h-4 w-4" /></span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $boton }} {{ $activo }}" aria-label="Anterior"><x-icono nombre="izquierda" class="h-4 w-4" /></a>
            @endif

            {{-- Páginas (en celular solo la actual) --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="{{ $boton }} hidden text-marca-suave sm:inline-flex" aria-disabled="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="{{ $boton }} bg-marca-bosque text-white shadow" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="{{ $boton }} {{ $activo }} hidden sm:inline-flex" aria-label="Ir a la página {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Siguiente --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $boton }} {{ $activo }}" aria-label="Siguiente"><x-icono nombre="derecha" class="h-4 w-4" /></a>
            @else
                <span class="{{ $boton }} {{ $inactivo }}" aria-disabled="true" aria-label="Siguiente"><x-icono nombre="derecha" class="h-4 w-4" /></span>
            @endif
        </div>
    </nav>
@endif
