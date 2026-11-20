@php
    $iconos = [
        'usuarios.index' => 'usuarios',
        'categorias.index' => 'categorias',
        'equipos.index' => 'equipos',
        'prestamos.index' => 'prestamos',
        'reportes.prestamos' => 'reporte',
        'noticias.index' => 'noticias',
        'prestamos.pendientes' => 'reloj',
        'entregas.index' => 'llave',
        'devoluciones.index' => 'devolucion',
        'configuracion.edit' => 'ajustes',
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

    {{-- Acceso rápido: los módulos del rol, cada uno con su color. --}}
    <nav class="mt-8" aria-labelledby="titulo-acceso">
        <h2 id="titulo-acceso" class="font-display text-xs font-bold uppercase tracking-wider text-marca-suave">Acceso rápido</h2>
        <div class="mt-3 flex flex-wrap gap-2.5">
            @foreach (Auth::user()->modulos() as $etiqueta => $ruta)
                <a href="{{ route($ruta) }}" class="group inline-flex items-center gap-2 rounded-full py-1.5 pl-1.5 pr-4 font-display text-sm font-bold transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_8px_18px_-10px_rgba(11,61,42,0.6)] focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/60 {{ $colores[$loop->index % count($colores)] }}">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-white/25 ring-1 ring-white/30">
                        <x-icono :nombre="$iconos[$ruta] ?? 'info'" class="h-4 w-4" />
                    </span>
                    {{ $etiqueta }}
                </a>
            @endforeach
        </div>
    </nav>

    {{-- Para hoy (personal): lo que espera atención. --}}
    @if ($paraHoy)
        <section class="mt-8" aria-labelledby="titulo-hoy">
            <h2 id="titulo-hoy" class="font-display text-xs font-bold uppercase tracking-wider text-marca-suave">Para hoy</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($paraHoy as $etiqueta => [$cantidad, $ruta, $parametros, $icono])
                    <a href="{{ route($ruta, $parametros) }}" class="flex items-center gap-3 rounded-3xl border border-marca-linea bg-white p-4 transition hover:-translate-y-0.5 hover:border-marca-lima hover:shadow-[0_10px_24px_-16px_rgba(11,61,42,0.6)] focus:outline-none focus-visible:ring-4 focus-visible:ring-marca-lima/60">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl {{ $cantidad > 0 ? 'bg-marca-lima text-marca-noche' : 'bg-marca-crema text-marca-suave' }}"><x-icono :nombre="$icono" class="h-5 w-5" /></span>
                        <span>
                            <span class="block font-display text-2xl font-black leading-none text-marca-noche">{{ $cantidad }}</span>
                            <span class="text-sm font-semibold text-marca-suave">{{ $etiqueta }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Noticias: las ven todos; solo el administrador y el funcionario ven el botón para crear. --}}
    <section class="mt-8 border-t border-marca-linea pt-8" aria-labelledby="titulo-noticias">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 id="titulo-noticias" class="flex items-center gap-3 font-display font-extrabold text-xl text-marca-noche">
                <span class="h-6 w-1.5 rounded-full bg-marca-lima" aria-hidden="true"></span>
                Noticias
            </h2>
            <div class="flex flex-wrap items-center gap-4">
                @if ($noticias->isNotEmpty())
                    <a class="enlace text-sm" href="{{ route('noticias.index') }}">Ver todas</a>
                @endif
                @can('create', \App\Models\Noticia::class)
                    <a class="boton-principal text-sm" href="{{ route('noticias.create') }}"><x-icono nombre="mas" class="h-4 w-4" />Crear noticia</a>
                @endcan
            </div>
        </div>

        @if ($noticias->isEmpty())
            <p class="mt-5 rounded-2xl border-2 border-dashed border-marca-linea px-6 py-8 text-center text-marca-suave">Todavía no hay noticias publicadas.</p>
        @else
            <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($noticias as $noticia)
                    @include('noticias.tarjeta')
                @endforeach
            </div>
        @endif
    </section>
</x-modulo>
