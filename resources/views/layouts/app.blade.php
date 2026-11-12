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
    <body class="font-sans antialiased text-marca-tinta">
        <div class="flex min-h-screen flex-col bg-marca-crema">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="banda">
                    {{-- Formas institucionales: círculos lima y verde --}}
                    <div class="pointer-events-none absolute -right-16 -top-24 h-72 w-72 rounded-full bg-marca-lima" aria-hidden="true"></div>
                    <div class="pointer-events-none absolute right-40 -bottom-20 h-44 w-44 rounded-full border-[18px] border-marca-hoja/60" aria-hidden="true"></div>
                    <div class="pointer-events-none absolute right-20 top-10 hidden h-16 w-16 rounded-2xl bg-marca-noche/60 rotate-12 md:block" aria-hidden="true"></div>
                    <div class="relative max-w-7xl mx-auto px-4 pt-10 pb-20 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main @class(['flex-1', 'relative -mt-12' => isset($header)])>
                {{ $slot }}
            </main>

            <footer class="mt-12 bg-marca-noche text-white/70">
                <div class="franja-uts h-1.5" aria-hidden="true"></div>
                <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-3 px-4 py-6 text-sm sm:px-6 lg:px-8">
                    <span class="flex items-center gap-3">
                        <x-application-logo class="h-8 w-8" />
                        <span class="font-display font-bold text-white">Préstamo de equipos</span>
                    </span>
                    <span>Proyecto Final Integrador · Grupo 5</span>
                </div>
            </footer>
        </div>
    </body>
</html>
