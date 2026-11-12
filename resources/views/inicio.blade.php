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
                <span>Proyecto Final Integrador · Grupo 5</span>
                <span class="hidden sm:inline">Desarrollo de Aplicaciones Empresariales · Tercer corte</span>
            </div>
        </div>

        <header class="bg-white border-b border-marca-linea">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between gap-4">
                <a href="{{ route('inicio') }}" class="flex items-center gap-3 sm:gap-4">
                    <img src="{{ asset('images/logo-uts.svg') }}" alt="Logo de la Universitaria Tecnológica de Santander" class="h-12 sm:h-14 w-auto shrink-0">
                    <span class="hidden sm:block h-10 w-px bg-marca-linea" aria-hidden="true"></span>
                    <span class="hidden sm:block leading-tight">
                        <span class="block font-display font-extrabold text-lg sm:text-xl text-marca-noche">Préstamo de equipos</span>
                        <span class="block text-sm text-marca-suave">Sistema de gestión</span>
                    </span>
                </a>

                @auth
                    <a href="{{ route('dashboard') }}" class="boton-principal shrink-0">Ir a la plataforma</a>
                @else
                    <a href="{{ route('login') }}" class="boton-principal shrink-0">Iniciar sesión</a>
                @endauth
            </div>
        </header>

        <section class="relative overflow-hidden bg-gradient-to-br from-marca-noche via-marca-bosque to-marca-oliva text-white">
            <div class="absolute inset-0 opacity-20 [background-image:radial-gradient(rgba(255,255,255,.55)_1.5px,transparent_1.5px)] [background-size:26px_26px]" aria-hidden="true"></div>

            {{-- Ilustración propia: los recursos que se prestan --}}
            <div class="hidden xl:grid absolute right-16 top-14 grid-cols-3 gap-5 -rotate-6 text-marca-lima" aria-hidden="true">
                @foreach ([
                    'M4 6h16v10H4zM2 19h20',
                    'M3 8h18v8H3zM8 16v3M16 16v3M7 12h.01M14 12a2 2 0 1 0 4 0a2 2 0 1 0-4 0',
                    'M7 4v5a3 3 0 0 0 3 3h4a3 3 0 0 1 3 3v5M4 4h6M14 20h6',
                    'M6 21V4h12v17M3 21h18M14 12h.01',
                    'M12 3l8 3v6c0 5-3.5 8-8 9c-4.5-1-8-4-8-9V6zM9 12l2 2l4-4',
                    'M12 7v5l3 2M3 12a9 9 0 1 0 18 0a9 9 0 1 0-18 0',
                ] as $i => $icono)
                    <div @class(['flex h-32 w-32 items-center justify-center rounded-2xl border border-white/20 bg-white/10 backdrop-blur-sm', 'translate-y-8' => $i % 3 === 1])>
                        <svg viewBox="0 0 24 24" class="h-16 w-16" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icono }}" /></svg>
                    </div>
                @endforeach
            </div>

            <div class="relative max-w-6xl mx-auto px-4 sm:px-6 pt-16 pb-36 sm:pt-20 lg:pb-44">
                <div class="max-w-xl">
                    <span class="inline-flex items-center gap-2 rounded bg-marca-morado px-3 py-1.5 text-sm font-semibold">
                        <span class="h-2 w-2 rounded-full bg-marca-lima" aria-hidden="true"></span>
                        Página provisional
                    </span>

                    <h1 class="mt-6 font-display font-extrabold text-4xl sm:text-5xl leading-[1.12]">
                        Proyecto final
                        <span class="block">del tercer corte</span>
                        <span class="block text-marca-lima">en construcción</span>
                    </h1>

                    <p class="mt-6 text-lg text-white/85 leading-relaxed">
                        Aquí estará el sistema para gestionar el préstamo de equipos y salones. Esta portada es temporal: el diseño definitivo se definirá más adelante.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="boton-claro">Ir a la plataforma <span aria-hidden="true">→</span></a>
                        @else
                            <a href="{{ route('login') }}" class="boton-claro">Iniciar sesión <span aria-hidden="true">→</span></a>
                        @endauth
                        <a href="#proyecto" class="inline-flex items-center rounded-md border border-white/40 bg-white/10 px-5 py-3 font-semibold hover:bg-white/20 transition">Conocer el proyecto</a>
                    </div>
                </div>
            </div>

            {{-- Cinta de obra --}}
            <div class="absolute -left-[5%] bottom-12 w-[110%] -rotate-2 border-y-2 border-marca-noche bg-marca-lima py-2 text-marca-noche" aria-hidden="true">
                <div class="whitespace-nowrap text-center font-display font-extrabold text-sm tracking-[.2em] uppercase">
                    @for ($i = 0; $i < 8; $i++)
                        <span class="mx-4">En construcción</span><span>◆</span>
                    @endfor
                </div>
            </div>
        </section>

        <main id="proyecto" class="max-w-6xl mx-auto px-4 sm:px-6 py-12">
            <p class="text-lg">Prototipo académico para controlar la solicitud, la entrega y la devolución de equipos y salones. Usa datos de prueba.</p>

            <div class="mt-8 grid gap-6 lg:grid-cols-3">
                <section class="tarjeta lg:col-span-2">
                    <h2 class="titulo-seccion">Qué incluirá</h2>
                    <ul class="mt-5 grid gap-x-8 gap-y-3 sm:grid-cols-2">
                        @foreach ([
                            'Solicitud de equipos y salones',
                            'Aprobación, rechazo y entrega',
                            'Devoluciones y préstamos vencidos',
                            'Reporte en línea con exportación a PDF',
                            'Cuatro tipos de usuario con sus permisos',
                            'Autenticación de dos factores opcional',
                        ] as $funcion)
                            <li class="flex gap-3 border-b border-marca-linea pb-3">
                                <span class="font-bold text-marca-oliva" aria-hidden="true">›</span>
                                <span>{{ $funcion }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <section class="tarjeta">
                    <h2 class="titulo-seccion">Equipo</h2>
                    <dl class="mt-5 space-y-4 text-sm">
                        <div>
                            <dt class="font-semibold text-marca-noche">Estudiantes</dt>
                            <dd>Néstor David García Rondón</dd>
                            <dd>Juan Esteban Hernández Esparza</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-marca-noche">Docente</dt>
                            <dd>Inaldo Javier Palacio González</dd>
                        </div>
                    </dl>
                </section>
            </div>
        </main>

        <footer class="bg-marca-noche text-white/80 text-sm">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-6">
                Proyecto académico en construcción. Esta página no es un sitio oficial de la institución.
            </div>
        </footer>
    </body>
</html>
