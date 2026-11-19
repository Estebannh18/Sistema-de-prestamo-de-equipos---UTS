<?php

namespace App\Notifications;

use App\Models\Prestamo;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Aviso a los funcionarios: hay una solicitud que necesita aprobación.
 * Solo dentro de la app (canal database). Se borra para todos cuando alguien la atiende.
 */
class SolicitudPorAprobar extends Notification
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
     * Mismo formato que en el taller 2FA: título, mensaje, enlace y tipo. Además, la solicitud,
     * para poder quitar el aviso de todos cuando se atiende.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $prestamo = $this->prestamo->loadMissing(['equipo.categoria', 'solicitante.programa']);
        $solicitante = $prestamo->solicitante;
        $carrera = $solicitante->programa ? ' ('.$solicitante->programa->nombre.')' : '';
        $esEspacio = $prestamo->equipo->esEspacio();

        return [
            'title' => $esEspacio ? 'Nueva solicitud de espacio' : 'Solicitud de equipo por aprobar',
            'message' => "{$solicitante->name}{$carrera} pidió «{$prestamo->equipo->nombre}» para el {$prestamo->fechaLarga()}, de {$prestamo->bloque()}",
            'link' => route('prestamos.show', $prestamo),
            'type' => 'por_aprobar',
            'prestamo_id' => $prestamo->id,
        ];
    }
}
