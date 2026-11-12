@php
    $iconos = [
        'usuarios.index' => 'usuarios',
        'categorias.index' => 'categorias',
        'equipos.index' => 'equipos',
        'prestamos.index' => 'prestamos',
        'prestamos.create' => 'solicitud',
        'reportes.prestamos' => 'reporte',
        'seguridad.2fa' => 'seguridad',
    ];
    // Colores de las tarjetas de módulos, en el orden del menú
    $colores = [
        'bg-marca-lima text-marca-noche',
        'bg-marca-bosque text-white',
        'bg-marca-hoja text-white',
        'bg-marca-noche text-white',
        'bg-marca-limaclaro text-marca-noche',
        'bg-marca-oliva text-white',
    ];
@endphp

<x-modulo titulo="Inicio" :pendiente="false">
    <div class="flex flex-wrap items-center gap-5">
        <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-3xl bg-marca-lima font-display text-2xl font-black text-marca-noche shadow-[0_5px_0_0_#7b8305]">
            {{ mb_strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}
        </span>
        <p class="text-lg">Hola, {{ Auth::user()->name }}. Tu rol es <strong class="rounded-full bg-marca-limaclaro px-2.5 py-0.5 font-display font-extrabold text-marca-bosque">{{ Auth::user()->rol }}</strong>.</p>
    </div>

    <h2 class="mt-10 flex items-center gap-3 font-display font-extrabold text-xl text-marca-noche">
        <span class="h-6 w-1.5 rounded-full bg-marca-lima" aria-hidden="true"></span>
        Tus módulos
    </h2>
    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach (Auth::user()->modulos() as $etiqueta => $ruta)
            <a href="{{ route($ruta) }}" class="group relative flex min-h-[132px] flex-col justify-between overflow-hidden rounded-3xl p-5 font-display text-lg font-extrabold transition duration-200 hover:-translate-y-1 hover:shadow-[0_14px_30px_-12px_rgba(11,61,42,0.55)] {{ $colores[$loop->index % count($colores)] }}">
                <span class="pointer-events-none absolute -bottom-8 -right-8 h-28 w-28 rounded-full bg-white/15 transition-transform duration-300 group-hover:scale-125" aria-hidden="true"></span>
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/20 ring-1 ring-white/30">
                    <x-icono :nombre="$iconos[$ruta] ?? 'info'" class="h-6 w-6" />
                </span>
                <span class="relative flex items-center justify-between">
                    {{ $etiqueta }}
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-white/25 font-bold transition group-hover:translate-x-1" aria-hidden="true">›</span>
                </span>
            </a>
        @endforeach
    </div>
</x-modulo>
