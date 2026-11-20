<?php

namespace App\Notifications;

use App\Models\Devolucion;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Aviso al solicitante con el resultado de la revisión de su devolución:
 * aceptada (con o sin pendiente), por corregir (motivo y plazo) o rechazada (motivo).
 */
class DevolucionRespondida extends Notification
{
    use Queueable;

    public function __construct(public Devolucion $devolucion) {}

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
        $devolucion = $this->devolucion->loadMissing('prestamo.equipo');
        $prestamo = $devolucion->prestamo;
        $nombre = "«{$prestamo->equipo->nombre}»";

        [$titulo, $mensaje, $tipo] = match ($devolucion->resultado) {
            'aceptada' => [
                'Devolución recibida',
                "Recibimos {$nombre}. Gracias.".($devolucion->pendiente ? " Queda un pendiente: {$devolucion->pendiente}" : ''),
                'devolucion_aceptada',
            ],
            'por_corregir' => [
                'Devolución por corregir',
                "No se recibió {$nombre}: {$devolucion->motivo}".($devolucion->plazo ? " Plazo: {$devolucion->plazo}." : ''),
                'por_corregir',
            ],
            default => [
                'Devolución rechazada',
                "La devolución de {$nombre} fue rechazada. Motivo: {$devolucion->motivo}",
                'devolucion_rechazada',
            ],
        };

        return [
            'title' => $titulo,
            'message' => $mensaje,
            'link' => route('prestamos.show', $prestamo),
            'type' => $tipo,
            'prestamo_id' => $prestamo->id,
        ];
    }
}
