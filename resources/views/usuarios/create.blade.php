<x-modulo titulo="Crear usuario" :pendiente="false">
    <form method="POST" action="{{ route('usuarios.store') }}" novalidate>
        @csrf
        @include('usuarios.formulario', ['usuario' => null])
        <div class="mt-8 flex flex-wrap items-center gap-4 border-t border-marca-linea pt-6">
            <x-primary-button>Guardar</x-primary-button>
            <a class="enlace text-sm" href="{{ route('usuarios.index') }}">Cancelar</a>
        </div>
    </form>
</x-modulo>
