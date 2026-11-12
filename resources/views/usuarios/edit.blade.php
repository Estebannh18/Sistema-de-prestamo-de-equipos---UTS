<x-modulo titulo="Editar usuario" :pendiente="false">
    <form method="POST" action="{{ route('usuarios.update', $usuario) }}" class="max-w-xl">
        @csrf
        @method('PUT')
        @include('usuarios.formulario')
        <x-primary-button class="mt-4">Guardar</x-primary-button>
    </form>
</x-modulo>
