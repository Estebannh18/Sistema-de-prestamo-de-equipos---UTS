<?php

namespace App\Policies;

use App\Models\Noticia;
use App\Models\User;

/**
 * Quién puede hacer qué con las noticias.
 * Ver: todos los usuarios con sesión. Crear: administrador y funcionario.
 * Editar y borrar: el administrador, cualquiera; el funcionario, solo las que publicó.
 */
class NoticiaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Noticia $noticia): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->esAdministrador() || $user->esFuncionario();
    }

    public function update(User $user, Noticia $noticia): bool
    {
        return $user->esAdministrador() || ($user->esFuncionario() && $noticia->user_id === $user->id);
    }

    public function delete(User $user, Noticia $noticia): bool
    {
        return $this->update($user, $noticia);
    }
}
