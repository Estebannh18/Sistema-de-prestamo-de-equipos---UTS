{{-- Fotos de una entrega o de una revisión de la devolución. Se abren con la sesión iniciada. --}}
@if ($fotos->isNotEmpty())
    <ul class="mt-2 flex flex-wrap gap-2" aria-label="Fotos">
        @foreach ($fotos as $foto)
            <li>
                <a href="{{ route('prestamos.foto', $foto) }}" target="_blank" rel="noopener" title="{{ $foto->nombre_original }}"
                   class="block overflow-hidden rounded-xl ring-1 ring-marca-linea transition hover:ring-2 hover:ring-marca-lima focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/60">
                    <img src="{{ route('prestamos.foto', $foto) }}" alt="Foto: {{ $foto->nombre_original }}" class="h-20 w-20 object-cover" loading="lazy">
                </a>
            </li>
        @endforeach
    </ul>
@endif
