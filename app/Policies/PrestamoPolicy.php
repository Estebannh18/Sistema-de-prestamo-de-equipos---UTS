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
        return $user->esAdministrador() || $user->esFuncionario() || (int) $prestamo->solicitante_id === (int) $user->id;
    }

    /**
     * Estudiantes y docentes abren la pestaña Solicitud.
     */
    public function create(User $user): bool
    {
        return $user->esDocente() || $user->esEstudiante();
    }

    /**
     * Por ahora solo los estudiantes envían solicitudes: la de los docentes queda para una etapa siguiente.
     */
    public function solicitar(User $user): bool
    {
        return $user->esEstudiante();
    }

    /**
     * El funcionario y el administrador atienden las solicitudes (el administrador no recibe avisos).
     */
    public function atender(User $user, ?Prestamo $prestamo = null): bool
    {
        return $user->esAdministrador() || $user->esFuncionario();
    }

    /**
     * Solo quien hizo la solicitud la cancela. El límite de tiempo lo revisa PrestamoService.
     */
    public function cancelar(User $user, Prestamo $prestamo): bool
    {
        return (int) $prestamo->solicitante_id === (int) $user->id;
    }
}
