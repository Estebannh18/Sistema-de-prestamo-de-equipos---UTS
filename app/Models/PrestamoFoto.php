<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Foto de una entrega o de una revisión de la devolución. Se guarda en el disco privado
 * («local») y solo se ve con la sesión iniciada.
 */
class PrestamoFoto extends Model
{
    /** Formatos de foto aceptados (extensiones). */
    public const EXTENSIONES = ['jpg', 'jpeg', 'png', 'webp'];

    /** Tamaño máximo de cada foto, en KB (5 MB). */
    public const MAX_KB = 5120;

    protected $fillable = ['prestamo_id', 'entrega_id', 'devolucion_id', 'ruta', 'nombre_original', 'tipo_mime', 'tamano'];

    protected function casts(): array
    {
        return ['tamano' => 'integer'];
    }

    protected static function booted(): void
    {
        // Al borrar la foto se borra también su archivo.
        static::deleting(fn (PrestamoFoto $foto) => Storage::disk('local')->delete($foto->ruta));
    }

    /**
     * Reglas de validación de las fotos de una entrega o devolución (campo «fotos»).
     * extensions revisa el nombre del archivo; mimes revisa su contenido real.
     *
     * @return array<string, array<int, string>>
     */
    public static function reglas(): array
    {
        $tipos = implode(',', self::EXTENSIONES);

        return [
            'fotos' => ['nullable', 'array', 'max:'.Configuracion::maximoFotos()],
            'fotos.*' => ['bail', 'file', "extensions:{$tipos}", "mimes:{$tipos}", 'max:'.self::MAX_KB],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function mensajes(): array
    {
        $maximo = Configuracion::maximoFotos();

        return [
            'fotos.max' => $maximo === 0 ? 'No se pueden subir fotos.' : "Puedes subir máximo {$maximo} ".($maximo === 1 ? 'foto' : 'fotos').'.',
            'fotos.*.file' => 'La foto :position no se pudo subir. Revisa que pese menos de 5 MB.',
            'fotos.*.uploaded' => 'La foto :position no se pudo subir. Revisa que pese menos de 5 MB.',
            'fotos.*.extensions' => 'La foto :position debe ser JPG, JPEG, PNG o WebP.',
            'fotos.*.mimes' => 'La foto :position debe ser JPG, JPEG, PNG o WebP.',
            'fotos.*.max' => 'La foto :position pesa más de 5 MB.',
        ];
    }

    public function prestamo(): BelongsTo
    {
        return $this->belongsTo(Prestamo::class);
    }
}
