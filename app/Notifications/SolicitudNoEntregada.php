<?php

namespace App\Notifications;

use App\Models\Prestamo;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Aviso al solicitante cuando su préstamo no se pudo entregar, con el motivo.
 */
class SolicitudNoEntregada extends Notification
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

        return [
            'title' => 'No se pudo entregar',
            'message' => "Tu préstamo de «{$prestamo->equipo->nombre}» del {$prestamo->fechaLarga()}, de {$prestamo->bloque()}, no se pudo entregar. Motivo: {$prestamo->motivo_no_entrega}",
            'link' => route('prestamos.show', $prestamo),
            'type' => 'no_entregado',
            'prestamo_id' => $prestamo->id,
        ];
    }
}
