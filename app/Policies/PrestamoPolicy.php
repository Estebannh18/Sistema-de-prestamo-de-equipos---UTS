<?php

namespace App\Policies;

use App\Models\Prestamo;
use App\Models\User;

class PrestamoPolicy
{
    /**
     * El personal ve todos los préstamos; estudiantes y docentes, solo los suyos.
     */
    public function view(User $user, Prestamo $prestamo): bool
    {
        return $user->esAdministrador() || $user->esFuncionario() || $prestamo->solicitante_id === $user->id;
    }

    /**
     * Solo estudiantes y docentes solicitan préstamos.
     */
    public function create(User $user): bool
    {
        return $user->esDocente() || $user->esEstudiante();
    }

    /**
     * Solo el personal aprueba, rechaza o registra devoluciones.
     */
    public function atender(User $user, Prestamo $prestamo): bool
    {
        return $user->esAdministrador() || $user->esFuncionario();
    }
}
