<x-modulo titulo="Noticias" :pendiente="false">
    @if (session('status'))
        <p class="aviso-ok"><x-icono nombre="check" class="h-5 w-5 shrink-0" />{{ session('status') }}</p>
    @endif

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-marca-suave">
            Avisos de la dependencia de préstamos: salas cerradas, equipos nuevos o reparados y otras novedades.
            {{ $noticias->total() }} {{ $noticias->total() === 1 ? 'noticia' : 'noticias' }}.
        </p>
        @can('create', \App\Models\Noticia::class)
            <a class="boton-principal text-sm" href="{{ route('noticias.create') }}"><x-icono nombre="mas" class="h-4 w-4" />Crear noticia</a>
        @endcan
    </div>

    @if ($noticias->isEmpty())
        <p class="rounded-2xl border-2 border-dashed border-marca-linea px-6 py-10 text-center text-marca-suave">Todavía no hay noticias publicadas.</p>
    @else
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($noticias as $noticia)
                @include('noticias.tarjeta')
            @endforeach
        </div>
    @endif

    @if ($noticias->hasPages())
        <nav class="mt-6 flex flex-wrap items-center justify-between gap-3" aria-label="Páginas de noticias">
            <p class="text-sm text-marca-suave">Página {{ $noticias->currentPage() }} de {{ $noticias->lastPage() }}</p>
            @php($botonPagina = 'inline-flex items-center justify-center gap-2 rounded-full border-2 border-marca-oliva bg-white px-6 py-2 font-display text-sm font-bold text-marca-oliva transition hover:bg-marca-oliva hover:text-white focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/50')
            <div class="flex gap-3">
                @if ($noticias->onFirstPage())
                    <x-secondary-button disabled>Anterior</x-secondary-button>
                @else
                    <a class="{{ $botonPagina }}" href="{{ $noticias->previousPageUrl() }}" rel="prev">Anterior</a>
                @endif
                @if ($noticias->hasMorePages())
                    <a class="{{ $botonPagina }}" href="{{ $noticias->nextPageUrl() }}" rel="next">Siguiente</a>
                @else
                    <x-secondary-button disabled>Siguiente</x-secondary-button>
                @endif
            </div>
        </nav>
    @endif
</x-modulo>
