<x-modulo :titulo="$tipo === 'espacio' ? 'Editar espacio' : 'Editar equipo'" :pendiente="false">
    <form method="POST" action="{{ route('equipos.update', $equipo) }}">
        @csrf
        @method('PUT')
        @include('equipos.formulario')
        <div class="mt-5 flex items-center gap-4">
            <x-primary-button>Guardar</x-primary-button>
            <a class="enlace text-sm" href="{{ route('equipos.index') }}">Cancelar</a>
        </div>
    </form>
</x-modulo>
