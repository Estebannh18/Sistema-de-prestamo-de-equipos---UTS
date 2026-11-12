<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        @include('layouts.fuentes')

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-marca-tinta bg-marca-crema">
        <div class="min-h-screen lg:grid lg:grid-cols-2">
            <aside class="relative hidden overflow-hidden bg-marca-bosque p-12 text-white lg:flex lg:flex-col lg:justify-between">
                {{-- Formas institucionales --}}
                <div class="pointer-events-none absolute -right-28 top-1/4 h-96 w-96 rounded-full bg-marca-oliva" aria-hidden="true"></div>
                <div class="pointer-events-none absolute -left-16 -bottom-16 h-64 w-64 rounded-full bg-marca-lima" aria-hidden="true"></div>
                <div class="pointer-events-none absolute right-24 bottom-28 h-28 w-28 rounded-full border-[16px] border-marca-hoja/60" aria-hidden="true"></div>
                <div class="pointer-events-none absolute right-16 top-16 grid grid-cols-2 gap-3" aria-hidden="true">
                    <span class="h-16 w-16 rounded-2xl bg-marca-lima -rotate-6"></span>
                    <span class="h-16 w-16 rounded-2xl bg-white/90 rotate-3 translate-y-4"></span>
                    <span class="h-16 w-16 rounded-2xl bg-marca-noche rotate-6"></span>
                    <span class="h-16 w-16 rounded-2xl bg-marca-hoja -rotate-3 translate-y-4"></span>
                </div>

                <a href="{{ route('inicio') }}" class="relative flex items-center gap-3 font-display text-xl font-extrabold">
                    <x-application-logo class="h-12 w-12" />
                    Préstamo de equipos
                </a>
                <div class="relative mb-24">
                    <p class="font-display text-5xl font-black leading-[1.05] tracking-tight">Proyecto final del tercer corte</p>
                    <p class="mt-4 inline-block -rotate-1 rounded-2xl bg-marca-lima px-3 pb-1 font-display text-3xl font-black text-marca-noche">en construcción</p>
                </div>
            </aside>

            <main class="relative flex min-h-screen flex-col items-center justify-center px-4 py-10 lg:min-h-0">
                <div class="franja-uts absolute inset-x-0 top-0 h-2 lg:hidden" aria-hidden="true"></div>

                <a href="{{ route('inicio') }}" class="mb-6 flex items-center gap-3 font-display text-xl font-extrabold text-marca-noche lg:hidden">
                    <x-application-logo class="h-11 w-11" />
                    Préstamo de equipos
                </a>

                <img src="{{ asset('images/logo-uts.svg') }}" alt="Logo de la Universitaria Tecnológica de Santander" class="mb-6 hidden h-16 w-auto lg:block">

                <div class="tarjeta w-full overflow-hidden sm:max-w-md">
                    <div class="franja-uts absolute inset-x-0 top-0 h-2" aria-hidden="true"></div>
                    {{ $slot }}
                </div>

                <a href="{{ route('inicio') }}" class="enlace mt-6 text-sm">Volver al inicio</a>
            </main>
        </div>
    </body>
</html>
