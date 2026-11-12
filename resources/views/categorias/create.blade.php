<x-modulo titulo="Crear categoría" :pendiente="false">
    <form method="POST" action="{{ route('categorias.store') }}" class="max-w-xl">
        @csrf
        @include('categorias.formulario', ['categoria' => null])
        <div class="mt-5 flex items-center gap-4">
            <x-primary-button>Guardar</x-primary-button>
            <a class="enlace text-sm" href="{{ route('categorias.index') }}">Cancelar</a>
        </div>
    </form>
</x-modulo>
