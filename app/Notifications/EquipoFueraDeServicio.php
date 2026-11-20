<?php

namespace App\Notifications;

use App\Models\Prestamo;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Aviso a los administradores: un espacio o equipo quedó fuera de servicio porque su devolución
 * se rechazó (llegó dañado).
 */
class EquipoFueraDeServicio extends Notification
{
    use Queueable;

    public function __construct(public Prestamo $prestamo, public string $motivo) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $prestamo = $this->prestamo->loadMissing(['equipo', 'solicitante']);

        return [
            'title' => 'Equipo fuera de servicio',
            'message' => "«{$prestamo->equipo->nombre}» quedó fuera de servicio: se rechazó la devolución de {$prestamo->solicitante->name}. Motivo: {$this->motivo}",
            'link' => route('prestamos.show', $prestamo),
            'type' => 'devolucion_rechazada',
            'prestamo_id' => $prestamo->id,
        ];
    }
}
