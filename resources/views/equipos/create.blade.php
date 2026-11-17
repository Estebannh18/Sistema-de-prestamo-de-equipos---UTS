<x-modulo :titulo="$tipo === 'espacio' ? 'Crear espacio' : 'Crear equipo'" :pendiente="false">
    @if ($categorias->isEmpty())
        <p class="aviso-error">Primero crea una <a class="underline" href="{{ route('categorias.create') }}">categoría</a> de {{ $tipo === 'espacio' ? 'espacios' : 'equipos' }}.</p>
    @endif

    <form method="POST" action="{{ route('equipos.store') }}" class="max-w-2xl">
        @csrf
        @include('equipos.formulario')
        <div class="mt-5 flex items-center gap-4">
            <x-primary-button>Guardar</x-primary-button>
            <a class="enlace text-sm" href="{{ route('equipos.index') }}">Cancelar</a>
        </div>
    </form>
</x-modulo>
