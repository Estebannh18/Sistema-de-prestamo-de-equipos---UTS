<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fotos y documentos adjuntos a cada noticia.
     * El archivo se guarda en storage/app/private (no es público); aquí queda su ubicación y sus datos.
     */
    public function up(): void
    {
        Schema::create('noticia_adjuntos', function (Blueprint $table) {
            $table->id();
            // Si se borra la noticia, se borran sus registros de adjuntos.
            $table->foreignId('noticia_id')->constrained('noticias')->cascadeOnDelete();
            $table->enum('tipo', ['imagen', 'documento']);
            $table->string('ruta');
            $table->string('nombre_original');
            $table->string('mime', 150);
            $table->unsignedInteger('tamano'); // en bytes
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('noticia_adjuntos');
    }
};
