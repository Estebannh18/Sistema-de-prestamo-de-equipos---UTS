<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

/**
 * Notificaciones dentro de la app (canal database), con el mismo patrón del taller 2FA:
 * bandeja, abrir (marca leída y lleva al enlace) y marcar todas como leídas.
 * Además, un resumen en JSON para que la campana se actualice sola cada 30 segundos.
 */
class NotificacionController extends Controller
{
    /** Cuántas notificaciones sin leer se muestran en el menú de la campana. */
    public const EN_CAMPANA = 6;

    public function index(Request $request): View
    {
        return view('notificaciones.index', [
            'notificaciones' => $request->user()->notifications()->paginate(15),
        ]);
    }

    /**
     * Marca la notificación como leída y lleva a su enlace (por ejemplo, la solicitud por aprobar).
     * Si ya no existe es porque otro funcionario atendió la solicitud.
     */
    public function abrir(Request $request, string $id): RedirectResponse
    {
        $notificacion = $request->user()->notifications()->find($id);

        if (! $notificacion) {
            return redirect()->route('notificaciones.index')->with('status', 'Ese aviso ya no está: la solicitud ya fue atendida.');
        }

        $notificacion->markAsRead();

        return redirect($notificacion->data['link'] ?? route('notificaciones.index'));
    }

    public function leerTodas(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'Todas las notificaciones quedaron como leídas.');
    }

    /**
     * Lo que muestra la campana: cuántas sin leer y las más recientes.
     */
    public function resumen(Request $request): JsonResponse
    {
        return response()->json(self::datosCampana($request->user()));
    }

    /**
     * @return array{total: int, items: array<int, array<string, string>>}
     */
    public static function datosCampana($user): array
    {
        $sinLeer = $user->unreadNotifications();

        return [
            'total' => (clone $sinLeer)->count(),
            'items' => (clone $sinLeer)->take(self::EN_CAMPANA)->get()->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'titulo' => $n->data['title'] ?? 'Notificación',
                'mensaje' => $n->data['message'] ?? '',
                'tipo' => $n->data['type'] ?? '',
                'hace' => $n->created_at->locale('es')->diffForHumans(),
                'abrir' => route('notificaciones.abrir', $n->id),
            ])->all(),
        ];
    }
}
