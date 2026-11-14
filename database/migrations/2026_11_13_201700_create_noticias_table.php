<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Noticias y avisos que publican el administrador y el funcionario en el inicio.
     */
    public function up(): void
    {
        Schema::create('noticias', function (Blueprint $table) {
            $table->id();
            // Quién la publicó. Si ese usuario se borra, la noticia se conserva sin autor.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('titulo', 150);
            $table->text('contenido');
            $table->timestamps();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('noticias');
    }
};
