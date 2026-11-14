<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Noticia o aviso del inicio. La publican el administrador o el funcionario y la ven todos.
 */
class Noticia extends Model
{
    /** @use HasFactory<\Database\Factories\NoticiaFactory> */
    use HasFactory;

    /** Cuántos archivos puede tener una noticia, y cuánto puede pesar cada uno (en KB). */
    public const MAX_ADJUNTOS = 5;

    public const MAX_KB_POR_ARCHIVO = 5120;

    /** Extensiones aceptadas. */
    public const EXTENSIONES_IMAGEN = ['jpg', 'jpeg', 'png', 'webp'];

    public const EXTENSIONES_DOCUMENTO = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];

    protected $fillable = ['titulo', 'contenido'];

    protected static function booted(): void
    {
        // Al borrar la noticia se borra también su carpeta de archivos.
        static::deleting(fn (Noticia $noticia) => Storage::disk('local')->deleteDirectory($noticia->carpeta()));
    }

    /** De la más reciente a la más antigua, con su autor y sus archivos ya cargados. */
    public function scopeRecientes(Builder $consulta): Builder
    {
        return $consulta->with(['autor', 'adjuntos'])->latest()->orderByDesc('id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function adjuntos(): HasMany
    {
        return $this->hasMany(NoticiaAdjunto::class);
    }

    public function imagenes(): HasMany
    {
        return $this->adjuntos()->where('tipo', 'imagen');
    }

    public function documentos(): HasMany
    {
        return $this->adjuntos()->where('tipo', 'documento');
    }

    /** Carpeta donde se guardan sus archivos, dentro de storage/app/private. */
    public function carpeta(): string
    {
        return "noticias/{$this->id}";
    }

    /** Nombre de quien la publicó, o un texto genérico si ese usuario ya no existe. */
    public function nombreAutor(): string
    {
        return $this->autor?->name ?? 'Usuario eliminado';
    }
}
