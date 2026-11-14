<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Foto o documento adjunto a una noticia.
 */
class NoticiaAdjunto extends Model
{
    protected $fillable = ['tipo', 'ruta', 'nombre_original', 'mime', 'tamano'];

    protected function casts(): array
    {
        return ['tamano' => 'integer'];
    }

    protected static function booted(): void
    {
        // Al quitar un adjunto se borra también su archivo.
        static::deleting(fn (NoticiaAdjunto $adjunto) => Storage::disk('local')->delete($adjunto->ruta));
    }

    public function noticia(): BelongsTo
    {
        return $this->belongsTo(Noticia::class);
    }

    public function esImagen(): bool
    {
        return $this->tipo === 'imagen';
    }

    /** Extensión en mayúsculas para mostrarla, por ejemplo «PDF». */
    public function extension(): string
    {
        return Str::upper(pathinfo($this->nombre_original, PATHINFO_EXTENSION));
    }

    /** Tamaño legible, por ejemplo «1,2 MB» o «350 KB». */
    public function tamanoLegible(): string
    {
        return $this->tamano >= 1048576
            ? number_format($this->tamano / 1048576, 1, ',', '.').' MB'
            : max(1, (int) round($this->tamano / 1024)).' KB';
    }
}
