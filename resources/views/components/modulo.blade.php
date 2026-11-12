@props(['titulo', 'pendiente' => true])

<x-app-layout>
    <x-slot name="header">
        <h1 class="titulo-seccion">{{ $titulo }}</h1>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="tarjeta overflow-x-auto">
                @if ($pendiente)
                    <p class="mb-5 inline-flex items-center gap-2 rounded bg-marca-morado px-3 py-1 text-sm font-semibold text-white">
                        <span class="h-2 w-2 rounded-full bg-marca-lima" aria-hidden="true"></span>
                        En construcción: por ahora este módulo solo muestra su estructura.
                    </p>
                @endif

                {{ $slot }}
            </div>
        </div>
    </div>
</x-app-layout>
