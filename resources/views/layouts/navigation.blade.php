@php
    // Icono de cada módulo del menú (los módulos salen del rol, en User::modulos())
    $iconos = [
        'usuarios.index' => 'usuarios',
        'categorias.index' => 'categorias',
        'equipos.index' => 'equipos',
        'prestamos.index' => 'prestamos',
        'prestamos.create' => 'solicitud',
        'reportes.prestamos' => 'reporte',
        'seguridad.2fa' => 'seguridad',
    ];
    $iniciales = collect(explode(' ', Auth::user()->name))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
@endphp

<nav x-data="{ open: false }" class="relative z-30">
    {{-- Cinta superior --}}
    <div class="bg-marca-noche text-white/85 text-xs">
        <div class="max-w-7xl mx-auto flex items-center gap-2 px-4 sm:px-6 lg:px-8 py-1.5">
            <span class="h-2 w-2 rounded-full bg-marca-lima" aria-hidden="true"></span>
            Proyecto Final Integrador · En construcción
        </div>
    </div>

    {{-- Encabezado con logo --}}
    <div class="bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex h-20 items-center justify-between gap-4">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-4">
                    <img src="{{ asset('images/logo-uts.svg') }}" alt="Logo de la Universitaria Tecnológica de Santander" class="h-12 w-auto shrink-0">
                    <span class="hidden h-10 w-px bg-marca-linea sm:block" aria-hidden="true"></span>
                    <span class="hidden items-center gap-3 sm:flex">
                        <x-application-logo class="h-10 w-10" />
                        <span class="leading-tight">
                            <span class="block font-display text-lg font-extrabold text-marca-noche">Préstamo de equipos</span>
                            <span class="block text-xs font-semibold text-marca-suave">Sistema de gestión</span>
                        </span>
                    </span>
                </a>

                {{-- Menú del usuario --}}
                <div class="hidden lg:flex lg:items-center">
                    <x-dropdown align="right" width="w-60">
                        <x-slot name="trigger">
                            <button class="group inline-flex items-center gap-3 rounded-full border-2 border-marca-linea bg-white py-1.5 pl-1.5 pr-4 text-sm font-semibold text-marca-tinta transition hover:border-marca-lima focus:outline-none">
                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-marca-lima font-display text-sm font-extrabold text-marca-noche">{{ $iniciales }}</span>
                                <span class="text-left leading-tight">
                                    <span class="block">{{ Auth::user()->name }}</span>
                                    <span class="block text-xs font-medium capitalize text-marca-suave">{{ Auth::user()->rol }}</span>
                                </span>
                                <svg class="h-4 w-4 fill-current text-marca-suave transition group-hover:text-marca-oliva" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile.edit')">
                                <x-icono nombre="perfil" class="h-4 w-4 text-marca-oliva" />
                                Perfil
                            </x-dropdown-link>

                            <!-- Authentication -->
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf

                                <x-dropdown-link :href="route('logout')"
                                        onclick="event.preventDefault();
                                                    this.closest('form').submit();">
                                    <x-icono nombre="salir" class="h-4 w-4 text-marca-oliva" />
                                    Cerrar sesión
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>

                {{-- Hamburguesa --}}
                <div class="flex items-center lg:hidden">
                    <button @click="open = ! open" class="inline-flex items-center justify-center rounded-full bg-marca-bosque p-2.5 text-white transition hover:bg-marca-noche focus:outline-none" aria-label="Menú">
                        <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                            <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Barra de navegación horizontal --}}
    <div class="hidden bg-marca-bosque lg:block">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-1 py-2.5">
                <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                    <x-icono nombre="inicio" class="h-[18px] w-[18px]" />
                    Inicio
                </x-nav-link>
                @foreach (Auth::user()->modulos() as $etiqueta => $ruta)
                    <x-nav-link :href="route($ruta)" :active="request()->routeIs($ruta)">
                        <x-icono :nombre="$iconos[$ruta] ?? 'info'" class="h-[18px] w-[18px]" />
                        {{ $etiqueta }}
                    </x-nav-link>
                @endforeach
            </div>
        </div>
    </div>
    <div class="franja-uts h-1.5" aria-hidden="true"></div>

    {{-- Menú en celular --}}
    <div x-cloak x-show="open" x-transition.opacity class="absolute inset-x-0 top-full z-50 bg-marca-noche shadow-2xl lg:hidden">
        <div class="space-y-1 px-4 pt-4 pb-3">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                <x-icono nombre="inicio" class="h-5 w-5" />
                Inicio
            </x-responsive-nav-link>
            @foreach (Auth::user()->modulos() as $etiqueta => $ruta)
                <x-responsive-nav-link :href="route($ruta)" :active="request()->routeIs($ruta)">
                    <x-icono :nombre="$iconos[$ruta] ?? 'info'" class="h-5 w-5" />
                    {{ $etiqueta }}
                </x-responsive-nav-link>
            @endforeach
        </div>

        <!-- Responsive Settings Options -->
        <div class="border-t border-white/10 px-4 pt-4 pb-5">
            <div class="flex items-center gap-3 px-4">
                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-marca-lima font-display text-sm font-extrabold text-marca-noche">{{ $iniciales }}</span>
                <div>
                    <div class="font-display font-bold text-white">{{ Auth::user()->name }}</div>
                    <div class="text-sm text-white/60">{{ Auth::user()->email }}</div>
                </div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    <x-icono nombre="perfil" class="h-5 w-5" />
                    Perfil
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        <x-icono nombre="salir" class="h-5 w-5" />
                        Cerrar sesión
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
