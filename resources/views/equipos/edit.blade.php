<x-modulo titulo="Editar equipo o salón" :pendiente="false">
    <form method="POST" action="{{ route('equipos.update', $equipo) }}" class="max-w-xl">
        @csrf
        @method('PUT')
        @include('equipos.formulario')
        <div class="mt-5 flex items-center gap-4">
            <x-primary-button>Guardar</x-primary-button>
            <a class="enlace text-sm" href="{{ route('equipos.index') }}">Cancelar</a>
        </div>
    </form>
</x-modulo>
