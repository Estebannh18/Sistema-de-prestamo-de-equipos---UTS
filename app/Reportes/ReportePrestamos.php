<?php

namespace App\Reportes;

use App\Models\Categoria;
use App\Models\Configuracion;
use App\Models\Prestamo;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Reporte de préstamos (RF-14). Lo usan la vista en línea y el PDF, así los dos muestran
 * exactamente lo mismo para los mismos filtros. Cada consulta lee la base de datos (RNF-10).
 *
 * Las fechas filtran por el día del préstamo (la fecha en que se usa el equipo o espacio).
 */
class ReportePrestamos
{
    /**
     * Grupos de estados del reporte: clave => etiqueta. Agrupan los diez estados del préstamo para que
     * el resumen se lea de un vistazo. «Atrasado» no se guarda: es un entregado cuya hora límite de
     * devolución ya pasó (Prestamo::estaAtrasado).
     */
    public const ESTADOS = [
        'pendiente' => 'Pendientes',
        'aprobado' => 'Aprobadas',
        'entregado' => 'En préstamo',
        'atrasado' => 'Devolución atrasada',
        'devuelto' => 'Devueltos',
        'novedad' => 'Con novedad',
        'cerrado' => 'Rechazadas o canceladas',
    ];

    /** Estados del préstamo que entran en cada grupo (los entregados se separan por la hora límite). */
    public const GRUPOS = [
        'pendiente' => ['pendiente'],
        'aprobado' => ['aprobado'],
        'entregado' => ['entregado'],
        'atrasado' => ['entregado'],
        'devuelto' => ['devuelto'],
        'novedad' => ['no_recogido', 'no_entregado', 'por_corregir', 'devolucion_rechazada'],
        'cerrado' => ['rechazado', 'cancelado'],
    ];

    /** Explicación de cada grupo, para la ayuda de los indicadores. */
    public const AYUDAS = [
        'pendiente' => 'Esperan la aprobación de un funcionario.',
        'aprobado' => 'Aprobadas y todavía sin entregar.',
        'entregado' => 'Entregadas y dentro del tiempo de devolución.',
        'atrasado' => 'Entregadas y ya pasó la hora límite de devolución.',
        'devuelto' => 'Devueltos y aceptados.',
        'novedad' => 'No recogidos, no entregados, por corregir o con devolución rechazada.',
        'cerrado' => 'Rechazadas por un funcionario o canceladas.',
    ];

    /** Máximo de días que abarca un reporte: un año. */
    public const MAX_DIAS = 366;

    public readonly Carbon $desde;

    public readonly Carbon $hasta;

    public readonly ?string $estado;

    public readonly ?string $tipo;

    public readonly ?int $categoria;

    public readonly bool $mios;

    public function __construct(public readonly User $usuario, array $filtros = [])
    {
        $this->desde = isset($filtros['desde']) ? Carbon::parse($filtros['desde'])->startOfDay() : today()->startOfMonth();
        $this->hasta = isset($filtros['hasta']) ? Carbon::parse($filtros['hasta'])->startOfDay() : today()->endOfMonth()->startOfDay();
        $this->estado = $filtros['estado'] ?? null;
        $this->tipo = $filtros['tipo'] ?? null;
        $this->categoria = isset($filtros['categoria']) ? (int) $filtros['categoria'] : null;
        $this->mios = (bool) ($filtros['mios'] ?? false);
    }

    /**
     * Valida los filtros de la petición. Un filtro inválido devuelve el formulario con el error.
     */
    public static function desdePeticion(Request $request): self
    {
        $filtros = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'estado' => ['nullable', Rule::in(array_keys(self::ESTADOS))],
            'tipo' => ['nullable', Rule::in(['equipo', 'espacio'])],
            'categoria' => ['nullable', 'integer', Rule::exists(Categoria::class, 'id')],
            'mios' => ['nullable', 'boolean'],
        ], [
            'hasta.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
        ]);

        $reporte = new self($request->user(), array_filter($filtros, fn ($valor) => $valor !== null && $valor !== ''));

        if ($reporte->desde->diffInDays($reporte->hasta) + 1 > self::MAX_DIAS) {
            throw ValidationException::withMessages(['hasta' => 'El reporte abarca como máximo un año.']);
        }

        return $reporte;
    }

    /** Filtros activos, para armar enlaces (paginación, exportar a PDF). */
    public function parametros(): array
    {
        return array_filter([
            'desde' => $this->desde->toDateString(),
            'hasta' => $this->hasta->toDateString(),
            'estado' => $this->estado,
            'tipo' => $this->tipo,
            'categoria' => $this->categoria,
            'mios' => $this->mios ? 1 : null,
        ]);
    }

    /** Descripción de los filtros en palabras, para el encabezado del PDF. */
    public function descripcionFiltros(): array
    {
        return array_filter([
            'Periodo' => $this->desde->format('d/m/Y').' a '.$this->hasta->format('d/m/Y'),
            'Estado' => $this->estado ? self::ESTADOS[$this->estado] : 'Todos',
            'Tipo' => match ($this->tipo) {
                'equipo' => 'Equipos', 'espacio' => 'Espacios', default => 'Equipos y espacios'
            },
            'Categoría' => $this->categoria ? Categoria::find($this->categoria)?->nombre : null,
            'Atendidos por' => $this->mios ? $this->usuario->name : null,
        ]);
    }

    // ---------- Consultas ----------

    /** Todos los filtros menos el estado: sobre esta base se cuentan los indicadores. */
    private function base(): Builder
    {
        return Prestamo::query()
            ->whereBetween('prestamos.fecha', [$this->desde->toDateString(), $this->hasta->toDateString()])
            ->when($this->tipo, fn (Builder $query) => $query->deTipo($this->tipo === 'espacio'))
            ->when($this->categoria, fn (Builder $query) => $query->whereHas('equipo', fn (Builder $query) => $query->where('categoria_id', $this->categoria)))
            ->when($this->mios, fn (Builder $query) => $query->where('prestamos.funcionario_id', $this->usuario->id));
    }

    /**
     * Entregados cuya hora límite de devolución (fin del bloque más el tiempo configurado) ya pasó.
     * Es la misma regla de Prestamo::estaAtrasado, pero en SQL para poder contar y filtrar.
     */
    private function soloAtrasados(Builder $query, bool $atrasados = true): Builder
    {
        $limite = now()->subMinutes(Configuracion::minutosDevolucion())->toDateTimeString();

        return $query->where('prestamos.estado', 'entregado')
            ->whereRaw('TIMESTAMP(prestamos.fecha, prestamos.hora_fin) '.($atrasados ? '<' : '>=').' ?', [$limite]);
    }

    /** La base con el grupo de estados elegido. */
    private function filtrada(): Builder
    {
        return $this->base()->when($this->estado, fn (Builder $query) => match ($this->estado) {
            'entregado' => $this->soloAtrasados($query, false),
            'atrasado' => $this->soloAtrasados($query),
            default => $query->whereIn('prestamos.estado', self::GRUPOS[$this->estado]),
        });
    }

    /**
     * Totales por grupo de estados y por forma de aprobación. No dependen del estado elegido,
     * para que siempre se vea el panorama completo del periodo.
     *
     * @return array{total: int, estados: array<string, int>, automatica: int, funcionario: int}
     */
    public function indicadores(): array
    {
        $porEstado = $this->base()->selectRaw('prestamos.estado, count(*) as total')->groupBy('prestamos.estado')->pluck('total', 'estado');
        $atrasados = $this->soloAtrasados($this->base())->count();
        $porAprobacion = $this->base()->whereNotNull('aprobacion')->selectRaw('aprobacion, count(*) as total')->groupBy('aprobacion')->pluck('total', 'aprobacion');

        $estados = [];
        foreach (self::GRUPOS as $grupo => $incluidos) {
            $estados[$grupo] = (int) match ($grupo) {
                'entregado' => ($porEstado['entregado'] ?? 0) - $atrasados,
                'atrasado' => $atrasados,
                default => collect($incluidos)->sum(fn ($estado) => $porEstado[$estado] ?? 0),
            };
        }

        return [
            'total' => array_sum($estados),
            'estados' => $estados,
            'automatica' => (int) ($porAprobacion['automatica'] ?? 0),
            'funcionario' => (int) ($porAprobacion['funcionario'] ?? 0),
        ];
    }

    /**
     * Solicitudes por día del periodo (con el estado elegido). Si el periodo pasa de dos meses,
     * se agrupa por mes para que la gráfica se pueda leer.
     *
     * @return Collection<int, array{etiqueta: string, total: int}>
     */
    public function porPeriodo(): Collection
    {
        $porMes = $this->desde->diffInDays($this->hasta) > 62;
        $conteos = $this->filtrada()->selectRaw('prestamos.fecha as dia, count(*) as total')->groupBy('prestamos.fecha')->pluck('total', 'dia')
            ->mapWithKeys(fn ($total, $fecha) => [Carbon::parse($fecha)->toDateString() => (int) $total]);

        $serie = collect();
        for ($dia = $this->desde->copy(); $dia->lte($this->hasta); $dia->addDay()) {
            $clave = $porMes ? $dia->format('Y-m') : $dia->toDateString();
            $etiqueta = $porMes ? ucfirst($dia->translatedFormat('M Y')) : $dia->format('d/m');
            $actual = $serie->get($clave, ['etiqueta' => $etiqueta, 'total' => 0]);
            $actual['total'] += $conteos[$dia->toDateString()] ?? 0;
            $serie->put($clave, $actual);
        }

        return $serie->values();
    }

    /**
     * Los equipos o espacios más pedidos.
     *
     * @return Collection<int, object{nombre: string, codigo: string, total: int}>
     */
    public function masPedidos(int $limite = 5): Collection
    {
        return $this->filtrada()
            ->join('equipos', 'equipos.id', '=', 'prestamos.equipo_id')
            ->select('equipos.nombre', 'equipos.codigo_inventario as codigo', DB::raw('count(*) as total'))
            ->groupBy('equipos.id', 'equipos.nombre', 'equipos.codigo_inventario')
            ->orderByDesc('total')->orderBy('equipos.nombre')
            ->limit($limite)->toBase()->get();
    }

    /**
     * Las carreras que más solicitan (de estudiantes y docentes con programa).
     * Un programa que existe en varias sedes cuenta como una sola carrera.
     *
     * @return Collection<int, object{nombre: string, total: int}>
     */
    public function carrerasQueMasPiden(int $limite = 5): Collection
    {
        return $this->filtrada()
            ->join('users', 'users.id', '=', 'prestamos.solicitante_id')
            ->join('programas', 'programas.id', '=', 'users.programa_id')
            ->select('programas.nombre', DB::raw('count(*) as total'))
            ->groupBy('programas.nombre')
            ->orderByDesc('total')->orderBy('programas.nombre')
            ->limit($limite)->toBase()->get();
    }

    /** Detalle, del más reciente al más antiguo. */
    private function detalle(): Builder
    {
        return $this->filtrada()
            ->with(['equipo.categoria', 'solicitante.programa', 'funcionario'])
            ->orderByDesc('prestamos.fecha')->orderByDesc('prestamos.hora_inicio')->orderByDesc('prestamos.id');
    }

    public function paginado(int $porPagina = 15): LengthAwarePaginator
    {
        return $this->detalle()->paginate($porPagina)->withQueryString();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Prestamo> */
    public function todos()
    {
        return $this->detalle()->get();
    }

    /** Estado con que se muestra un préstamo en el reporte (incluye «vencido»). */
    public static function estadoDe(Prestamo $prestamo): string
    {
        return $prestamo->estaAtrasado() ? 'atrasado' : $prestamo->estado;
    }

    /** Etiqueta del estado de un préstamo en el detalle. */
    public static function nombreEstadoDe(Prestamo $prestamo): string
    {
        return $prestamo->estaAtrasado() ? 'Devolución atrasada' : $prestamo->nombreEstado();
    }

    /** Clase de insignia del estado (el atrasado usa la de alerta). */
    public static function insigniaDe(Prestamo $prestamo): string
    {
        return 'insignia-'.($prestamo->estaAtrasado() ? 'vencido' : $prestamo->estado);
    }
}
