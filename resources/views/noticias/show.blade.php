<x-modulo titulo="Noticias" :pendiente="false">
    @if (session('status'))
        <p class="aviso-ok"><x-icono nombre="check" class="h-5 w-5 shrink-0" />{{ session('status') }}</p>
    @endif

    <a href="{{ route('noticias.index') }}" class="enlace inline-flex items-center gap-1 text-sm"><x-icono nombre="izquierda" class="h-4 w-4" />Todas las noticias</a>

    <article class="mt-6">
        <header class="flex flex-wrap items-start justify-between gap-4 border-b border-marca-linea pb-5">
            <div class="min-w-0">
                <h2 class="font-display text-2xl font-extrabold leading-tight text-marca-noche sm:text-3xl">{{ $noticia->titulo }}</h2>
                <p class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-marca-suave">
                    <x-icono nombre="calendario" class="h-4 w-4" />
                    <time datetime="{{ $noticia->created_at->toIso8601String() }}">{{ $noticia->created_at->format('d/m/Y, g:i a') }}</time>
                    <span aria-hidden="true">·</span>
                    <span>Publicada por {{ $noticia->nombreAutor() }}</span>
                    @if ($noticia->updated_at->gt($noticia->created_at->copy()->addMinute()))
                        <span aria-hidden="true">·</span>
                        <span>Editada el {{ $noticia->updated_at->format('d/m/Y') }}</span>
                    @endif
                </p>
            </div>

            @can('update', $noticia)
                <div class="flex shrink-0 flex-wrap items-center gap-3">
                    <a href="{{ route('noticias.edit', $noticia) }}" class="inline-flex items-center justify-center gap-2 rounded-full border-2 border-marca-oliva bg-white px-5 py-2 font-display text-sm font-bold text-marca-oliva transition hover:bg-marca-oliva hover:text-white focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/50">
                        <x-icono nombre="editar" class="h-4 w-4" />Editar
                    </a>
                    <form method="POST" action="{{ route('noticias.destroy', $noticia) }}" onsubmit="return confirm('¿Eliminar esta noticia y todos sus archivos? Esta acción no se puede deshacer.');">
                        @csrf
                        @method('DELETE')
                        <x-danger-button><x-icono nombre="eliminar" class="h-4 w-4" />Eliminar</x-danger-button>
                    </form>
                </div>
            @endcan
        </header>

        {{-- El texto se muestra tal cual se escribió, con sus saltos de línea. --}}
        <div class="mt-6 max-w-3xl whitespace-pre-line text-base leading-relaxed text-marca-noche">{{ $noticia->contenido }}</div>

        @php
            $imagenes = $noticia->adjuntos->where('tipo', 'imagen');
            $documentos = $noticia->adjuntos->where('tipo', 'documento');
        @endphp

        @if ($imagenes->isNotEmpty())
            <section class="mt-8">
                <h3 class="flex items-center gap-2 font-display text-base font-bold text-marca-noche"><x-icono nombre="imagen" class="h-5 w-5 text-marca-oliva" />Fotos</h3>
                <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($imagenes as $imagen)
                        <a href="{{ route('noticias.adjunto', $imagen) }}" target="_blank" rel="noopener" class="group block overflow-hidden rounded-2xl border border-marca-linea focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/60">
                            <img src="{{ route('noticias.adjunto', $imagen) }}" alt="{{ $imagen->nombre_original }}" class="h-56 w-full object-cover transition duration-300 group-hover:scale-105" loading="lazy">
                            <span class="sr-only">Ver la foto en tamaño completo</span>
                        </a>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-marca-suave">Haz clic en una foto para verla en tamaño completo.</p>
            </section>
        @endif

        @if ($documentos->isNotEmpty())
            <section class="mt-8">
                <h3 class="flex items-center gap-2 font-display text-base font-bold text-marca-noche"><x-icono nombre="clip" class="h-5 w-5 text-marca-oliva" />Documentos</h3>
                <ul class="mt-3 grid gap-3 sm:grid-cols-2">
                    @foreach ($documentos as $documento)
                        <li>
                            <a href="{{ route('noticias.adjunto', $documento) }}" class="flex items-center gap-3 rounded-2xl border border-marca-linea bg-marca-crema/60 px-4 py-3 transition hover:border-marca-oliva hover:bg-marca-limaclaro/60 focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/60">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-marca-bosque text-white"><x-icono nombre="documento" class="h-5 w-5" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-bold text-marca-noche">{{ $documento->nombre_original }}</span>
                                    <span class="text-xs text-marca-suave">{{ $documento->extension() }} · {{ $documento->tamanoLegible() }}</span>
                                </span>
                                <x-icono nombre="descargar" class="h-5 w-5 shrink-0 text-marca-oliva" />
                                <span class="sr-only">Descargar</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </article>
</x-modulo>
