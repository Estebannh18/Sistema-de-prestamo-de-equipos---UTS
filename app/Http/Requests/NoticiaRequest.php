<?php

namespace App\Http\Requests;

use App\Models\Noticia;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Validaciones al crear o editar una noticia.
 * Las decisiones están explicadas en infoDesarrollo/modulo-noticias.html.
 */
class NoticiaRequest extends FormRequest
{
    /**
     * Crear: administrador o funcionario. Editar: según NoticiaPolicy.
     */
    public function authorize(): bool
    {
        $noticia = $this->noticiaEditada();

        return $noticia
            ? (bool) $this->user()?->can('update', $noticia)
            : (bool) $this->user()?->can('create', Noticia::class);
    }

    /**
     * El título queda en una sola línea, sin espacios de más.
     * En el contenido se respetan los saltos de línea, pero se quitan los espacios del inicio y del final.
     */
    protected function prepareForValidation(): void
    {
        $titulo = $this->input('titulo');
        $contenido = $this->input('contenido');

        $this->merge([
            'titulo' => is_string($titulo) ? Str::squish($titulo) : $titulo,
            'contenido' => is_string($contenido) ? trim(str_replace("\r\n", "\n", $contenido)) : $contenido,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $extensiones = implode(',', [...Noticia::EXTENSIONES_IMAGEN, ...Noticia::EXTENSIONES_DOCUMENTO]);
        $noticia = $this->noticiaEditada();

        return [
            'titulo' => ['bail', 'required', 'string', 'min:5', 'max:150'],
            'contenido' => ['bail', 'required', 'string', 'min:10', 'max:5000'],
            'adjuntos' => ['nullable', 'array', 'max:'.Noticia::MAX_ADJUNTOS],
            // extensions revisa el nombre del archivo; mimes revisa su contenido real.
            // Así no pasa, por ejemplo, un programa .exe renombrado como .pdf.
            'adjuntos.*' => ['bail', 'file', "extensions:{$extensiones}", "mimes:{$extensiones}", 'max:'.Noticia::MAX_KB_POR_ARCHIVO],
            'quitar' => ['nullable', 'array'],
            'quitar.*' => ['integer', Rule::exists('noticia_adjuntos', 'id')->where('noticia_id', $noticia?->id ?? 0)],
        ];
    }

    /**
     * Al editar, la suma de los archivos que se quedan y los nuevos no puede pasar del máximo.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $noticia = $this->noticiaEditada();
            if (! $noticia || $validator->errors()->has('adjuntos')) {
                return;
            }

            $quedan = $noticia->adjuntos()->whereNotIn('id', (array) $this->input('quitar', []))->count();
            $nuevos = count((array) $this->file('adjuntos', []));
            if ($quedan + $nuevos > Noticia::MAX_ADJUNTOS) {
                $validator->errors()->add('adjuntos', 'La noticia puede tener máximo '.Noticia::MAX_ADJUNTOS." archivos. Ya tiene {$quedan}: quita alguno o sube menos.");
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $tipos = 'fotos JPG, PNG o WEBP, o documentos PDF, Word, Excel o PowerPoint';

        return [
            'titulo.required' => 'Escribe el título de la noticia.',
            'titulo.min' => 'El título debe tener al menos 5 caracteres.',
            'titulo.max' => 'El título no puede tener más de 150 caracteres.',
            'contenido.required' => 'Escribe el texto de la noticia.',
            'contenido.min' => 'El texto debe tener al menos 10 caracteres.',
            'contenido.max' => 'El texto no puede tener más de 5000 caracteres.',
            'adjuntos.max' => 'Puedes adjuntar máximo '.Noticia::MAX_ADJUNTOS.' archivos.',
            'adjuntos.*.file' => 'El archivo :position no se pudo subir. Revisa que pese menos de 5 MB.',
            'adjuntos.*.uploaded' => 'El archivo :position no se pudo subir. Revisa que pese menos de 5 MB.',
            'adjuntos.*.extensions' => "El archivo :position no es de un tipo permitido. Se aceptan {$tipos}.",
            'adjuntos.*.mimes' => "El archivo :position no es de un tipo permitido. Se aceptan {$tipos}.",
            'adjuntos.*.max' => 'El archivo :position pesa más de 5 MB.',
            'quitar.*.exists' => 'Uno de los archivos que quieres quitar no pertenece a esta noticia.',
        ];
    }

    private function noticiaEditada(): ?Noticia
    {
        $noticia = $this->route('noticia');

        return $noticia instanceof Noticia ? $noticia : null;
    }
}
