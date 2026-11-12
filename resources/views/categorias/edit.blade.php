<x-modulo titulo="Editar categoría" :pendiente="false">
    <form method="POST" action="{{ route('categorias.update', $categoria) }}" class="max-w-xl">
        @csrf
        @method('PUT')
        @include('categorias.formulario')
        <div class="mt-5 flex items-center gap-4">
            <x-primary-button>Guardar</x-primary-button>
            <a class="enlace text-sm" href="{{ route('categorias.index') }}">Cancelar</a>
        </div>
    </form>
</x-modulo>
