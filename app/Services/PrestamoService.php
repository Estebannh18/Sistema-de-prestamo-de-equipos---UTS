<?php

namespace App\Services;

use App\Models\Equipo;
use App\Models\Prestamo;
use App\Models\User;
use LogicException;

/**
 * Reglas del préstamo (Figura 4 del documento 9). Por ahora solo está la estructura:
 * cada método se implementa en su módulo.
 */
class PrestamoService
{
    public function solicitar(Equipo $equipo, User $solicitante): Prestamo
    {
        throw new LogicException('Pendiente: módulo Solicitudes.');
    }

    public function aprobar(Prestamo $prestamo, User $funcionario): void
    {
        throw new LogicException('Pendiente: módulo Préstamos.');
    }

    public function rechazar(Prestamo $prestamo, User $funcionario): void
    {
        throw new LogicException('Pendiente: módulo Préstamos.');
    }

    public function registrarDevolucion(Prestamo $prestamo): void
    {
        throw new LogicException('Pendiente: módulo Devoluciones.');
    }
}
