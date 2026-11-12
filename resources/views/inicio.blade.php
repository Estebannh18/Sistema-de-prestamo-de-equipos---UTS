<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Proyecto final en construcción | {{ config('app.name') }}</title>
        <meta name="description" content="Proyecto final del tercer corte: sistema de gestión de préstamo de equipos, en construcción.">

        @include('layouts.fuentes')

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-marca-crema text-marca-tinta">
        {{-- Portada provisional: no es el diseño definitivo. --}}
        <div class="bg-marca-noche text-white/85 text-sm">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-2 flex flex-wrap justify-between gap-x-6">
                <span class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-marca-lima" aria-hidden="true"></span>Proyecto Final Integrador · Grupo 5</span>
                <span class="hidden sm:inline">Desarrollo de Aplicaciones Empresariales · Tercer corte</span>
            </div>
        </div>

        <header class="sticky top-0 z-30 bg-white shadow-[0_6px_20px_-14px_rgba(11,61,42,0.5)]">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between gap-4">
                <a href="{{ route('inicio') }}" class="flex items-center gap-3 sm:gap-4">
                    <img src="{{ asset('images/logo-uts.svg') }}" alt="Logo de la Universitaria Tecnológica de Santander" class="h-12 sm:h-14 w-auto shrink-0">
                    <span class="hidden sm:block h-10 w-px bg-marca-linea" aria-hidden="true"></span>
                    <span class="hidden sm:flex items-center gap-3">
                        <x-application-logo class="h-10 w-10" />
                        <span class="leading-tight">
                            <span class="block font-display font-extrabold text-lg sm:text-xl text-marca-noche">Préstamo de equipos</span>
                            <span class="block text-sm font-semibold text-marca-suave">Sistema de gestión</span>
                        </span>
                    </span>
                </a>

                @auth
                    <a href="{{ route('dashboard') }}" class="boton-principal shrink-0">Ir a la plataforma</a>
                @else
                    <a href="{{ route('login') }}" class="boton-principal shrink-0">Iniciar sesión</a>
                @endauth
            </div>
            <div class="franja-uts h-1.5" aria-hidden="true"></div>
        </header>

        <section class="relative overflow-hidden bg-marca-bosque text-white">
            {{-- Formas institucionales --}}
            <div class="pointer-events-none absolute -right-32 -top-32 h-[520px] w-[520px] rounded-full bg-marca-oliva" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -left-20 bottom-10 h-56 w-56 rounded-full border-[28px] border-marca-hoja/40" aria-hidden="true"></div>
            <div class="pointer-events-none absolute left-1/2 top-10 h-5 w-5 rounded-full bg-marca-lima" aria-hidden="true"></div>
            <div class="pointer-events-none absolute left-[42%] bottom-32 h-3 w-3 rounded-full bg-white/60" aria-hidden="true"></div>

            {{-- Ilustración propia: los recursos que se prestan --}}
            <div class="hidden xl:grid absolute right-14 top-14 grid-cols-3 gap-5" aria-hidden="true">
                @foreach ([
                    'M4 6h16v10H4zM2 19h20',
                    'M3 8h18v8H3zM8 16v3M16 16v3M7 12h.01M14 12a2 2 0 1 0 4 0a2 2 0 1 0-4 0',
                    'M7 4v5a3 3 0 0 0 3 3h4a3 3 0 0 1 3 3v5M4 4h6M14 20h6',
                    'M6 21V4h12v17M3 21h18M14 12h.01',
                    'M12 3l8 3v6c0 5-3.5 8-8 9c-4.5-1-8-4-8-9V6zM9 12l2 2l4-4',
                    'M12 7v5l3 2M3 12a9 9 0 1 0 18 0a9 9 0 1 0-18 0',
                ] as $i => $icono)
                    @php($estilo = ['bg-marca-lima text-marca-noche shadow-[0_8px_0_0_#7b8305]', 'bg-white text-marca-bosque shadow-[0_8px_0_0_#b9c7b0]', 'bg-marca-noche text-marca-lima shadow-[0_8px_0_0_#06261a]'][$i % 3])
                    <div @class(['flex h-32 w-32 items-center justify-center rounded-[2rem] transition duration-300 hover:-translate-y-2', $estilo, 'translate-y-10' => $i % 3 === 1, '-rotate-6' => $i % 2 === 0, 'rotate-3' => $i % 2 === 1])>
                        <svg viewBox="0 0 24 24" class="h-14 w-14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icono }}" /></svg>
                    </div>
                @endforeach
            </div>

            <div class="relative max-w-6xl mx-auto px-4 sm:px-6 pt-16 pb-36 sm:pt-20 lg:pb-44">
                <div class="max-w-xl">
                    <span class="inline-flex items-center gap-2 rounded-full bg-marca-lima px-4 py-1.5 font-display text-sm font-extrabold text-marca-noche">
                        <span class="h-2 w-2 rounded-full bg-marca-noche" aria-hidden="true"></span>
                        Página provisional
                    </span>

                    <h1 class="mt-6 font-display font-black text-4xl sm:text-6xl leading-[1.05] tracking-tight">
                        Proyecto final
                        <span class="block">del tercer corte</span>
                        <span class="mt-1 inline-block rounded-2xl bg-marca-lima px-3 pb-1 text-marca-noche -rotate-1">en construcción</span>
                    </h1>

                    <p class="mt-7 text-lg text-white/85 leading-relaxed">
                        Aquí estará el sistema para gestionar el préstamo de equipos y salones. Esta portada es temporal: el diseño definitivo se definirá más adelante.
                    </p>

                    <div class="mt-9 flex flex-wrap gap-4">
                        @auth
                            <a href="{{ route('dashboard') }}" class="boton-claro">Ir a la plataforma <span aria-hidden="true">→</span></a>
                        @else
                            <a href="{{ route('login') }}" class="boton-claro">Iniciar sesión <span aria-hidden="true">→</span></a>
                        @endauth
                        <a href="#proyecto" class="inline-flex items-center rounded-full border-2 border-white/70 px-6 py-3 font-display font-bold transition hover:bg-white hover:text-marca-bosque">Conocer el proyecto</a>
                    </div>
                </div>
            </div>

            {{-- Cinta de obra --}}
            <div class="absolute -left-[5%] bottom-12 w-[110%] -rotate-2 border-y-4 border-marca-noche bg-marca-lima py-2.5 text-marca-noche shadow-[0_10px_0_0_rgba(11,61,42,0.35)]" aria-hidden="true">
                <div class="whitespace-nowrap text-center font-display font-black text-sm tracking-[.2em] uppercase">
                    @for ($i = 0; $i < 8; $i++)
                        <span class="mx-4">En construcción</span><span>◆</span>
                    @endfor
                </div>
            </div>
        </section>

        <main id="proyecto" class="max-w-6xl mx-auto px-4 sm:px-6 py-14">
            <p class="max-w-3xl text-xl font-semibold leading-relaxed text-marca-noche">Prototipo académico para controlar la solicitud, la entrega y la devolución de equipos y salones. Usa datos de prueba.</p>

            <div class="mt-10 grid gap-6 lg:grid-cols-3">
                <section class="tarjeta lg:col-span-2">
                    <h2 class="titulo-seccion">Qué incluirá</h2>
                    <ul class="mt-6 grid gap-4 sm:grid-cols-2">
                        @foreach ([
                            ['Solicitud de equipos y salones', 'solicitud'],
                            ['Aprobación, rechazo y entrega', 'check'],
                            ['Devoluciones y préstamos vencidos', 'devolucion'],
                            ['Reporte en línea con exportación a PDF', 'reporte'],
                            ['Cuatro tipos de usuario con sus permisos', 'usuarios'],
                            ['Autenticación de dos factores opcional', 'seguridad'],
                        ] as $i => [$funcion, $icono])
                            <li class="group flex items-center gap-4 rounded-2xl bg-marca-crema p-3 pr-4 transition hover:bg-marca-limaclaro">
                                <span @class([
                                    'flex h-11 w-11 shrink-0 items-center justify-center rounded-xl transition group-hover:scale-110',
                                    'bg-marca-lima text-marca-noche' => $i % 3 === 0,
                                    'bg-marca-bosque text-white' => $i % 3 === 1,
                                    'bg-marca-hoja text-white' => $i % 3 === 2,
                                ])>
                                    <x-icono :nombre="$icono" class="h-5 w-5" />
                                </span>
                                <span class="font-semibold">{{ $funcion }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <section class="tarjeta overflow-hidden !bg-marca-noche !border-marca-noche text-white">
                    <div class="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full bg-marca-lima" aria-hidden="true"></div>
                    <h2 class="titulo-seccion relative !text-white">Equipo</h2>
                    <dl class="relative mt-6 space-y-5 text-sm">
                        <div>
                            <dt class="font-display font-bold uppercase tracking-wider text-xs text-marca-lima">Estudiantes</dt>
                            <dd class="mt-2 flex items-center gap-3"><span class="flex h-8 w-8 items-center justify-center rounded-full bg-white/15 font-display text-xs font-bold">NG</span>Néstor David García Rondón</dd>
                            <dd class="mt-2 flex items-center gap-3"><span class="flex h-8 w-8 items-center justify-center rounded-full bg-white/15 font-display text-xs font-bold">JH</span>Juan Esteban Hernández Esparza</dd>
                        </div>
                        <div>
                            <dt class="font-display font-bold uppercase tracking-wider text-xs text-marca-lima">Docente</dt>
                            <dd class="mt-2 flex items-center gap-3"><span class="flex h-8 w-8 items-center justify-center rounded-full bg-marca-lima font-display text-xs font-bold text-marca-noche">IP</span>Inaldo Javier Palacio González</dd>
                        </div>
                    </dl>
                </section>
            </div>
        </main>

        <footer class="bg-marca-noche text-white/80 text-sm">
            <div class="franja-uts h-1.5" aria-hidden="true"></div>
            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-6 flex flex-wrap items-center justify-between gap-3">
                <span class="flex items-center gap-3">
                    <x-application-logo class="h-8 w-8" />
                    Proyecto académico en construcción. Esta página no es un sitio oficial de la institución.
                </span>
            </div>
        </footer>
    </body>
</html>
