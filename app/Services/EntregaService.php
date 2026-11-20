<?php

namespace App\Services;

use App\Models\Configuracion;
use App\Models\Devolucion;
use App\Models\Entrega;
use App\Models\Equipo;
use App\Models\Prestamo;
use App\Models\PrestamoFoto;
use App\Models\User;
use App\Notifications\DevolucionRespondida;
use App\Notifications\EquipoFueraDeServicio;
use App\Notifications\SolicitudNoEntregada;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Entregas y devoluciones (funcionario y administrador).
 * - Entrega: solo de una aprobada, al solicitante en persona, desde la anticipación configurada
 *   hasta el fin del bloque, y si el préstamo anterior del mismo equipo ya se recibió.
 * - No se pudo entregar: con su motivo; al solicitante le llega el aviso.
 * - Devolución: cada revisión queda guardada. Aceptada (con o sin pendiente), por corregir
 *   (motivo y plazo como observación) o rechazada (el equipo queda fuera de servicio y sus
 *   solicitudes posteriores se cancelan con aviso).
 */
class EntregaService
{
    public function __construct(private PrestamoService $prestamos) {}

    /**
     * El préstamo anterior del mismo espacio o equipo que todavía no se ha recibido
     * (entregado o por corregir). Mientras exista, no se puede entregar.
     */
    public function sinDevolver(Prestamo $prestamo): ?Prestamo
    {
        return Prestamo::with('solicitante')
            ->where('equipo_id', $prestamo->equipo_id)
            ->whereKeyNot($prestamo->id)
            ->whereIn('estado', ['entregado', 'por_corregir'])
            ->orderBy('fecha')->orderBy('hora_inicio')
            ->first();
    }

    /**
     * Registra la entrega.
     *
     * @param  array{estado: string, complementos: string, condiciones?: ?string, observaciones?: ?string}  $datos
     * @param  array<int, UploadedFile>  $fotos
     */
    public function entregar(Prestamo $prestamo, User $funcionario, array $datos, array $fotos = []): Entrega
    {
        return DB::transaction(function () use ($prestamo, $funcionario, $datos, $fotos) {
            Equipo::whereKey($prestamo->equipo_id)->lockForUpdate()->first();
            $prestamo = Prestamo::whereKey($prestamo->id)->lockForUpdate()->firstOrFail();

            $this->exigir($prestamo->estado === 'aprobado', 'Solo se entrega una solicitud aprobada que todavía no se ha entregado.');
            $this->exigir(now()->greaterThanOrEqualTo($prestamo->inicioEntrega()),
                'Todavía no se puede entregar: se entrega desde las '.$prestamo->inicioEntrega()->locale('es')->isoFormat('h:mm a'));
            $this->exigir(now()->lessThan($prestamo->fin()), 'El bloque de esta solicitud ya terminó.');
            $anterior = $this->sinDevolver($prestamo);
            $this->exigir(! $anterior, $anterior ? $this->mensajeSinDevolver($anterior) : '');

            $entrega = $prestamo->entrega()->create([
                'funcionario_id' => $funcionario->id,
                'entregado_en' => now(),
                'estado' => $datos['estado'],
                'complementos' => $datos['complementos'],
                'condiciones' => $datos['condiciones'] ?? null,
                'observaciones' => $datos['observaciones'] ?? null,
            ]);
            $this->guardarFotos($prestamo, $fotos, ['entrega_id' => $entrega->id]);
            $prestamo->update(['estado' => 'entregado']);
            $prestamo->equipo->actualizarEstado();

            return $entrega;
        });
    }

    /** No se pudo entregar (por ejemplo, el anterior no lo devolvió): queda «No entregado» con su motivo. */
    public function noSePudoEntregar(Prestamo $prestamo, User $funcionario, string $motivo): void
    {
        DB::transaction(function () use ($prestamo, $funcionario, $motivo) {
            $prestamo = Prestamo::whereKey($prestamo->id)->lockForUpdate()->firstOrFail();
            $this->exigir($prestamo->estado === 'aprobado', 'Solo se marca una solicitud aprobada que todavía no se ha entregado.');
            $this->exigir(now()->greaterThanOrEqualTo($prestamo->inicioEntrega()), 'Todavía no es la hora de entregarla.');

            $prestamo->update(['estado' => 'no_entregado', 'motivo_no_entrega' => $motivo, 'no_entregado_por_id' => $funcionario->id, 'no_entregado_en' => now()]);
            $prestamo->solicitante->notify(new SolicitudNoEntregada($prestamo));
        });
    }

    /**
     * Registra una revisión de la devolución.
     *
     * @param  array<string, mixed>  $datos
     * @param  array<int, UploadedFile>  $fotos
     */
    public function recibir(Prestamo $prestamo, User $funcionario, array $datos, array $fotos = []): Devolucion
    {
        return DB::transaction(function () use ($prestamo, $funcionario, $datos, $fotos) {
            Equipo::whereKey($prestamo->equipo_id)->lockForUpdate()->first();
            $prestamo = Prestamo::whereKey($prestamo->id)->lockForUpdate()->firstOrFail();
            $this->exigir(in_array($prestamo->estado, ['entregado', 'por_corregir'], true), 'Esta solicitud no está pendiente de devolución.');

            $resultado = $datos['resultado'];
            $devolucion = $prestamo->devoluciones()->create([
                'funcionario_id' => $funcionario->id,
                'recibido_en' => now(),
                'minutos_retraso' => $prestamo->minutosDeRetraso(),
                'estado' => $datos['estado'],
                'complementos' => $datos['complementos'],
                'complementos_completos' => (bool) $datos['complementos_completos'],
                'faltantes' => $datos['complementos_completos'] ? null : ($datos['faltantes'] ?? null),
                'condiciones_cumplidas' => $datos['condiciones_cumplidas'] ?? null,
                'observaciones' => $datos['observaciones'] ?? null,
                'resultado' => $resultado,
                'motivo' => $resultado === 'aceptada' ? null : $datos['motivo'],
                'plazo' => $resultado === 'por_corregir' ? ($datos['plazo'] ?? null) : null,
                'pendiente' => $resultado === 'aceptada' && ! empty($datos['con_pendiente']) ? $datos['pendiente'] : null,
            ]);
            $this->guardarFotos($prestamo, $fotos, ['devolucion_id' => $devolucion->id]);

            $prestamo->update(['estado' => match ($resultado) {
                'aceptada' => 'devuelto',
                'por_corregir' => 'por_corregir',
                'rechazada' => 'devolucion_rechazada',
            }]);

            if ($resultado === 'rechazada') {
                $this->sacarDeServicio($prestamo, $funcionario, $datos['motivo']);
            }

            $prestamo->equipo->actualizarEstado();
            $prestamo->solicitante->notify(new DevolucionRespondida($devolucion));

            return $devolucion;
        });
    }

    /**
     * Solicitudes que se cancelarían si la devolución se rechaza: las pendientes o aprobadas
     * de ese espacio o equipo que todavía no empiezan (de ahora en adelante).
     *
     * @return Collection<int, Prestamo>
     */
    public function posteriores(Prestamo $prestamo): Collection
    {
        return Prestamo::with(['solicitante', 'equipo'])
            ->where('equipo_id', $prestamo->equipo_id)
            ->whereKeyNot($prestamo->id)
            ->whereIn('estado', Prestamo::CANCELABLES)
            ->whereDate('fecha', '>=', today()->toDateString())
            ->orderBy('fecha')->orderBy('hora_inicio')
            ->get()
            ->filter(fn (Prestamo $otro) => $otro->fin()?->isFuture())
            ->values();
    }

    /** El funcionario marca como resuelto el pendiente de una devolución aceptada. */
    public function resolverPendiente(Devolucion $devolucion, User $funcionario): void
    {
        $this->exigir($devolucion->tienePendienteSinResolver(), 'Esta devolución no tiene un pendiente por resolver.');
        $devolucion->update(['pendiente_resuelto_en' => now(), 'pendiente_resuelto_por_id' => $funcionario->id]);
    }

    /** Texto para avisar que el préstamo anterior no se ha recibido. */
    public function mensajeSinDevolver(Prestamo $anterior): string
    {
        return "Este equipo no se puede entregar porque no ha sido devuelto por {$anterior->solicitante->name} "
            ."(préstamo del {$anterior->fechaLarga()}, de {$anterior->bloque()}). "
            ."Comunícate con esa persona para que lo devuelva lo más pronto posible: {$anterior->solicitante->email}.";
    }

    /**
     * Rechazo absoluto: el equipo queda fuera de servicio (mantenimiento sin fecha de fin desde hoy)
     * y sus solicitudes posteriores se cancelan con aviso. Los administradores reciben un aviso.
     */
    private function sacarDeServicio(Prestamo $prestamo, User $funcionario, string $motivo): void
    {
        $equipo = $prestamo->equipo;
        $abierto = $equipo->mantenimientos()->whereNull('hasta')->first();

        if (! $abierto) {
            $equipo->mantenimientos()->create([
                'desde' => today(),
                'hasta' => null,
                'motivo' => 'Devolución rechazada: '.$motivo,
                'creado_por_id' => $funcionario->id,
                'prestamo_id' => $prestamo->id,
            ]);
        }

        $this->prestamos->cancelarPorElSistema($this->posteriores($prestamo), "«{$equipo->nombre}» quedó fuera de servicio.");

        Notification::send(User::where('rol', 'administrador')->where('activo', true)->get(), new EquipoFueraDeServicio($prestamo, $motivo));
    }

    /**
     * Guarda las fotos en el disco privado, en una carpeta por solicitud.
     *
     * @param  array<int, UploadedFile>  $fotos
     * @param  array<string, int>  $de  entrega_id o devolucion_id
     */
    private function guardarFotos(Prestamo $prestamo, array $fotos, array $de): void
    {
        foreach (array_slice($fotos, 0, Configuracion::maximoFotos()) as $foto) {
            PrestamoFoto::create($de + [
                'prestamo_id' => $prestamo->id,
                'ruta' => $foto->store("prestamos/{$prestamo->id}", 'local'),
                'nombre_original' => mb_substr($foto->getClientOriginalName(), 0, 255),
                'tipo_mime' => $foto->getMimeType() ?? 'image/jpeg',
                'tamano' => $foto->getSize(),
            ]);
        }
    }

    private function exigir(bool $condicion, string $mensaje): void
    {
        if (! $condicion) {
            throw ValidationException::withMessages(['solicitud' => $mensaje]);
        }
    }
}
