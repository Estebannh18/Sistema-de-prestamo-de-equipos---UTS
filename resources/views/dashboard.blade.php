<x-modulo titulo="Inicio" :pendiente="false">
    <p class="text-lg">Hola, {{ Auth::user()->name }}. Tu rol es <strong>{{ Auth::user()->rol }}</strong>.</p>

    <h2 class="mt-6 font-display font-bold text-lg text-marca-noche">Tus módulos</h2>
    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach (Auth::user()->modulos() as $etiqueta => $ruta)
            <a href="{{ route($ruta) }}" class="flex items-center justify-between rounded-lg border border-marca-linea px-4 py-3 font-medium text-marca-noche transition hover:border-marca-oliva hover:bg-marca-crema">
                {{ $etiqueta }}
                <span class="font-bold text-marca-oliva" aria-hidden="true">›</span>
            </a>
        @endforeach
    </div>
</x-modulo>
