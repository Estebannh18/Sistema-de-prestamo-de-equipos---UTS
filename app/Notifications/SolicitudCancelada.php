<?php

namespace App\Notifications;

use App\Models\Prestamo;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Aviso al solicitante cuando su solicitud la cancela el personal (con su motivo)
 * o el sistema (mantenimiento o equipo dañado).
 */
class SolicitudCancelada extends Notification
{
    use Queueable;

    public function __construct(public Prestamo $prestamo) {}

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
        $prestamo = $this->prestamo->loadMissing('equipo');
        $que = "«{$prestamo->equipo->nombre}» para el {$prestamo->fechaLarga()}, de {$prestamo->bloque()}";

        return [
            'title' => 'Solicitud cancelada',
            'message' => $prestamo->cancelada_por === 'sistema'
                ? "Tu solicitud de {$que} se canceló porque ya no está disponible: {$prestamo->motivo_cancelacion} Crea una nueva solicitud con los equipos o espacios disponibles."
                : "Tu solicitud de {$que} fue cancelada por la dependencia de préstamos. Motivo: {$prestamo->motivo_cancelacion}",
            'link' => route('prestamos.show', $prestamo),
            'type' => 'cancelada',
            'prestamo_id' => $prestamo->id,
        ];
    }
}
