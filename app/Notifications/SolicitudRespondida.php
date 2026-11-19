<?php

namespace App\Notifications;

use App\Models\Prestamo;
use App\Services\PrestamoService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Aviso al solicitante: su solicitud fue aprobada o rechazada (por un funcionario o automáticamente).
 * Solo dentro de la app (canal database).
 */
class SolicitudRespondida extends Notification
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
        $aprobada = $prestamo->estado === 'aprobado';
        $automatico = $prestamo->motivo_rechazo === PrestamoService::MOTIVO_AUTOMATICO;

        return [
            'title' => $aprobada ? 'Solicitud aprobada' : 'Solicitud rechazada',
            'message' => $aprobada
                ? "Tu solicitud de {$que} fue aprobada."
                : ($automatico
                    ? "Tu solicitud de {$que} se rechazó porque ya hay una solicitud aprobada para esa fecha y hora. Crea una nueva en los horarios disponibles."
                    : "Tu solicitud de {$que} fue rechazada. Motivo: {$prestamo->motivo_rechazo}"),
            'link' => route('prestamos.index').'#solicitud-'.$prestamo->id,
            'type' => $aprobada ? 'aprobada' : 'rechazada',
            'prestamo_id' => $prestamo->id,
        ];
    }
}
