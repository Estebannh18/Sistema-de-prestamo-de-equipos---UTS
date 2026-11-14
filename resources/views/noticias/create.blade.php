<x-modulo titulo="Crear noticia" :pendiente="false">
    {{-- enctype multipart/form-data: necesario para poder enviar archivos. --}}
    <form method="POST" action="{{ route('noticias.store') }}" enctype="multipart/form-data" novalidate>
        @csrf
        @include('noticias.formulario', ['noticia' => null])
        <div class="mt-8 flex flex-wrap items-center gap-4 border-t border-marca-linea pt-6">
            <x-primary-button>Publicar noticia</x-primary-button>
            <a class="enlace text-sm" href="{{ route('noticias.index') }}">Cancelar</a>
        </div>
    </form>
</x-modulo>
