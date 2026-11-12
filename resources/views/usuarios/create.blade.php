<x-modulo titulo="Crear usuario" :pendiente="false">
    <form method="POST" action="{{ route('usuarios.store') }}" class="max-w-xl">
        @csrf
        @include('usuarios.formulario', ['usuario' => null])
        <x-primary-button class="mt-4">Guardar</x-primary-button>
    </form>
</x-modulo>
