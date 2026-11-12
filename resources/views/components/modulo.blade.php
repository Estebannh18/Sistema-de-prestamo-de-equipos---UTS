@props(['titulo', 'pendiente' => true])

<x-app-layout>
    <x-slot name="header">
        <h1 class="titulo-seccion">{{ $titulo }}</h1>
    </x-slot>

    <div class="pb-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="tarjeta overflow-x-auto">
                @if ($pendiente)
                    <p class="mb-6 inline-flex items-center gap-2.5 overflow-hidden rounded-full bg-marca-limaclaro py-1.5 pl-1.5 pr-4 text-sm font-bold text-marca-noche ring-2 ring-marca-lima">
                        <span class="franja-uts flex h-6 w-6 items-center justify-center rounded-full" aria-hidden="true"></span>
                        En construcción: por ahora este módulo solo muestra su estructura.
                    </p>
                @endif

                {{ $slot }}
            </div>
        </div>
    </div>
</x-app-layout>
