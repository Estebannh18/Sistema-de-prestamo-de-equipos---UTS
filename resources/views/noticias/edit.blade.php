<x-modulo titulo="Editar noticia" :pendiente="false">
    <form method="POST" action="{{ route('noticias.update', $noticia) }}" enctype="multipart/form-data" novalidate>
        @csrf
        @method('PUT')
        @include('noticias.formulario')
        <div class="mt-8 flex flex-wrap items-center gap-4 border-t border-marca-linea pt-6">
            <x-primary-button>Guardar cambios</x-primary-button>
            <a class="enlace text-sm" href="{{ route('noticias.show', $noticia) }}">Cancelar</a>
        </div>
    </form>
</x-modulo>
