<x-modulo titulo="Crear equipo o salón" :pendiente="false">
    @if ($categorias->isEmpty())
        <p class="aviso-error">Primero crea una <a class="underline" href="{{ route('categorias.create') }}">categoría</a>.</p>
    @endif

    <form method="POST" action="{{ route('equipos.store') }}" class="max-w-xl">
        @csrf
        @include('equipos.formulario', ['equipo' => null])
        <div class="mt-5 flex items-center gap-4">
            <x-primary-button>Guardar</x-primary-button>
            <a class="enlace text-sm" href="{{ route('equipos.index') }}">Cancelar</a>
        </div>
    </form>
</x-modulo>
