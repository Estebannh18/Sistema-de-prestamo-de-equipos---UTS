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
            <aside class="relative hidden overflow-hidden bg-gradient-to-br from-marca-noche via-marca-bosque to-marca-oliva p-12 text-white lg:flex lg:flex-col lg:justify-between">
                <div class="absolute inset-0 opacity-20 [background-image:radial-gradient(rgba(255,255,255,.55)_1.5px,transparent_1.5px)] [background-size:26px_26px]" aria-hidden="true"></div>
                <a href="{{ route('inicio') }}" class="relative flex items-center gap-3 font-display text-xl font-extrabold">
                    <x-application-logo class="h-11 w-11" />
                    Préstamo de equipos
                </a>
                <div class="relative">
                    <p class="font-display text-4xl font-extrabold leading-tight">Proyecto final del tercer corte</p>
                    <p class="mt-3 font-display text-2xl font-bold text-marca-lima">en construcción</p>
                </div>
            </aside>

            <main class="flex min-h-screen flex-col items-center justify-center px-4 py-10 lg:min-h-0">
                <a href="{{ route('inicio') }}" class="mb-6 flex items-center gap-3 font-display text-xl font-extrabold text-marca-noche lg:hidden">
                    <x-application-logo class="h-11 w-11" />
                    Préstamo de equipos
                </a>

                <div class="tarjeta w-full sm:max-w-md">
                    {{ $slot }}
                </div>

                <a href="{{ route('inicio') }}" class="enlace mt-6 text-sm">Volver al inicio</a>
            </main>
        </div>
    </body>
</html>
