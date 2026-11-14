<?php

namespace App\Http\Controllers;

use App\Http\Requests\NoticiaRequest;
use App\Models\Noticia;
use App\Models\NoticiaAdjunto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NoticiaController extends Controller
{
    /** Noticias por página en la lista completa. */
    private const POR_PAGINA = 9;

    /**
     * Todas las noticias, de la más reciente a la más antigua. Las ven todos los usuarios.
     */
    public function index(): View
    {
        return view('noticias.index', [
            'noticias' => Noticia::query()->recientes()->paginate(self::POR_PAGINA),
        ]);
    }

    /**
     * Formulario para crear una noticia (administrador y funcionario).
     */
    public function create(): View
    {
        Gate::authorize('create', Noticia::class);

        return view('noticias.create');
    }

    /**
     * Guarda la noticia con sus archivos.
     */
    public function store(NoticiaRequest $request): RedirectResponse
    {
        $noticia = new Noticia($request->safe()->only(['titulo', 'contenido']));
        $noticia->user_id = $request->user()->id;
        $noticia->save();

        $this->guardarAdjuntos($noticia, $request->file('adjuntos', []));

        return redirect()->route('noticias.show', $noticia)->with('status', 'Noticia publicada. Ya la ven todos los usuarios.');
    }

    /**
     * Una noticia completa, con sus fotos y documentos.
     */
    public function show(Noticia $noticia): View
    {
        $noticia->load(['autor', 'adjuntos']);

        return view('noticias.show', ['noticia' => $noticia]);
    }

    /**
     * Formulario para editar una noticia.
     */
    public function edit(Noticia $noticia): View
    {
        Gate::authorize('update', $noticia);
        $noticia->load('adjuntos');

        return view('noticias.edit', ['noticia' => $noticia]);
    }

    /**
     * Actualiza el título y el texto, quita los archivos marcados y agrega los nuevos.
     */
    public function update(NoticiaRequest $request, Noticia $noticia): RedirectResponse
    {
        $noticia->update($request->safe()->only(['titulo', 'contenido']));

        // Se borran uno por uno para que también se borre cada archivo del disco.
        $noticia->adjuntos()->whereIn('id', (array) $request->input('quitar', []))->get()->each->delete();
        $this->guardarAdjuntos($noticia, $request->file('adjuntos', []));

        return redirect()->route('noticias.show', $noticia)->with('status', 'Noticia actualizada.');
    }

    /**
     * Borra la noticia y todos sus archivos.
     */
    public function destroy(Noticia $noticia): RedirectResponse
    {
        Gate::authorize('delete', $noticia);
        $noticia->delete();

        return redirect()->route('noticias.index')->with('status', "Se eliminó la noticia «{$noticia->titulo}».");
    }

    /**
     * Entrega un archivo adjunto. Solo con la sesión iniciada: los archivos no son públicos.
     * Las fotos se muestran en el navegador; los documentos se descargan con su nombre original.
     */
    public function adjunto(NoticiaAdjunto $adjunto): StreamedResponse
    {
        $disco = Storage::disk('local');
        abort_unless($disco->exists($adjunto->ruta), 404);

        return $adjunto->esImagen()
            ? $disco->response($adjunto->ruta, $adjunto->nombre_original, ['Content-Type' => $adjunto->mime])
            : $disco->download($adjunto->ruta, $adjunto->nombre_original);
    }

    /**
     * Guarda cada archivo en storage/app/private/noticias/{id} con un nombre aleatorio,
     * y registra su nombre original, tipo y tamaño.
     *
     * @param  array<int, UploadedFile>  $archivos
     */
    private function guardarAdjuntos(Noticia $noticia, array $archivos): void
    {
        foreach ($archivos as $archivo) {
            $noticia->adjuntos()->create([
                'tipo' => in_array(strtolower($archivo->extension()), Noticia::EXTENSIONES_IMAGEN, true) ? 'imagen' : 'documento',
                'ruta' => $archivo->store($noticia->carpeta(), 'local'),
                'nombre_original' => mb_substr($archivo->getClientOriginalName(), 0, 255),
                'mime' => mb_substr((string) $archivo->getMimeType(), 0, 150),
                'tamano' => $archivo->getSize(),
            ]);
        }
    }
}
