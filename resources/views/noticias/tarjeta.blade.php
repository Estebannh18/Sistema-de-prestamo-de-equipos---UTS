{{-- Tarjeta de una noticia: se usa en el inicio y en la lista de noticias. Recibe $noticia (con autor y adjuntos cargados). --}}
@php
    $portada = $noticia->adjuntos->firstWhere('tipo', 'imagen');
    $fotos = $noticia->adjuntos->where('tipo', 'imagen')->count();
    $documentos = $noticia->adjuntos->where('tipo', 'documento')->count();
@endphp

<article class="group relative flex flex-col overflow-hidden rounded-3xl border border-marca-linea bg-white transition duration-200 hover:-translate-y-1 hover:shadow-[0_14px_30px_-12px_rgba(11,61,42,0.45)]">
    @if ($portada)
        <img src="{{ route('noticias.adjunto', $portada) }}" alt="" class="h-44 w-full object-cover" loading="lazy">
    @else
        <div class="franja-uts h-2" aria-hidden="true"></div>
    @endif

    <div class="flex flex-1 flex-col p-5">
        <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-marca-suave">
            <x-icono nombre="calendario" class="h-4 w-4" />
            <time datetime="{{ $noticia->created_at->toDateString() }}">{{ $noticia->created_at->format('d/m/Y') }}</time>
            <span aria-hidden="true">·</span>
            <span>{{ $noticia->nombreAutor() }}</span>
        </p>

        <h3 class="mt-2 font-display text-lg font-extrabold leading-snug text-marca-noche">
            {{-- El enlace cubre toda la tarjeta, para poder hacer clic en cualquier parte. --}}
            <a href="{{ route('noticias.show', $noticia) }}" class="after:absolute after:inset-0 focus:outline-none focus-visible:underline">{{ $noticia->titulo }}</a>
        </h3>

        <p class="mt-2 flex-1 text-sm text-marca-noche/80">{{ \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish($noticia->contenido), 160) }}</p>

        <div class="mt-4 flex items-center justify-between gap-3 text-xs font-bold text-marca-oliva">
            <span class="flex items-center gap-3">
                @if ($fotos)
                    <span class="flex items-center gap-1"><x-icono nombre="imagen" class="h-4 w-4" />{{ $fotos }} {{ $fotos === 1 ? 'foto' : 'fotos' }}</span>
                @endif
                @if ($documentos)
                    <span class="flex items-center gap-1"><x-icono nombre="clip" class="h-4 w-4" />{{ $documentos }} {{ $documentos === 1 ? 'documento' : 'documentos' }}</span>
                @endif
            </span>
            <span class="flex items-center gap-1 transition group-hover:translate-x-1" aria-hidden="true">Leer más <x-icono nombre="derecha" class="h-4 w-4" /></span>
        </div>
    </div>
</article>
