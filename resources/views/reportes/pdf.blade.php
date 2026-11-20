@use('App\Reportes\ReportePrestamos')

{{--
    Reporte de préstamos en PDF (laravel-dompdf). Muestra lo mismo que la vista en línea con los mismos
    filtros, pero el detalle va completo.
    dompdf maneja poco flexbox y grid, así que el diseño usa tablas con anchos fijos: así nada se
    estira ni se parte. El número de página lo escribe el script del final (dompdf no calcula el
    total de páginas con CSS).
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de préstamos</title>
    <style>
        @page { margin: 33mm 14mm 17mm 14mm; }
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 8.5pt; color: #1d2a21; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }
        .suave { color: #5c6a60; }
        .num { text-align: right; }
        .pequena { font-size: 7pt; }

        /* Encabezado y pie, en todas las páginas */
        header { position: fixed; top: -27mm; left: 0; right: 0; height: 22mm; }
        header .franja { height: 1.6mm; background: #c7d309; margin-top: 2.5mm; }
        header .linea { height: 0.6mm; background: #0f5c3a; }
        header .titulo { font-size: 15pt; font-weight: bold; color: #0b3d2a; }
        header .generado { font-size: 7pt; color: #5c6a60; text-align: right; line-height: 1.5; }
        footer { position: fixed; bottom: -11mm; left: 0; right: 0; height: 6mm; font-size: 7pt; color: #5c6a60; border-top: 0.5pt solid #dde5d6; padding-top: 1.5mm; }

        /* Títulos de sección */
        h2 { font-size: 10pt; color: #0b3d2a; margin: 0 0 5pt; padding-left: 5pt; border-left: 3pt solid #c7d309; }
        .seccion { margin-top: 12pt; }

        /* Filtros */
        .filtros { background: #f4f7ef; border: 0.5pt solid #dde5d6; }
        .filtros td { padding: 5pt 8pt; width: 20%; }
        .filtros .etiqueta { font-size: 6.5pt; text-transform: uppercase; letter-spacing: 0.4pt; color: #5c6a60; }
        .filtros .valor { font-weight: bold; color: #0b3d2a; margin-top: 1pt; }

        /* Indicadores: tres filas (etiqueta, cifra, porcentaje) para que todo quede alineado */
        .indicadores { table-layout: fixed; }
        .indicadores td { width: 12.5%; padding: 0 6pt; border-left: 0.5pt solid #dde5d6; border-right: 0.5pt solid #dde5d6; }
        .indicadores tr.rotulos td { height: 17pt; padding-top: 6pt; border-top: 0.5pt solid #dde5d6; font-size: 6.5pt; text-transform: uppercase; letter-spacing: 0.3pt; color: #5c6a60; }
        .indicadores tr.cifras td { font-size: 16pt; font-weight: bold; line-height: 1.1; }
        .indicadores tr.porcentajes td { padding-bottom: 6pt; border-bottom: 0.5pt solid #dde5d6; font-size: 7pt; color: #5c6a60; }
        .indicadores td.total { background: #0f5c3a; color: #ffffff; border-color: #0f5c3a; }
        .indicadores tr.rotulos td.total { color: #c7d309; }
        .indicadores tr.porcentajes td.total { color: #d9e7dd; }
        .indicadores td.alerta { background: #fff1f2; }
        .indicadores tr.cifras td.alerta { color: #b4202a; }
        .punto { display: inline-block; width: 5pt; height: 5pt; border-radius: 2.5pt; margin-right: 2pt; }

        /* Barra de distribución */
        .distribucion { table-layout: fixed; margin-top: 5pt; }
        .distribucion td { height: 5pt; padding: 0; }

        /* Rankings */
        .ranking td { padding: 3.5pt 0; border-bottom: 0.5pt solid #e8ede4; vertical-align: middle; }
        .ranking .pos { width: 6mm; color: #5c6a60; }
        .ranking .barra-celda { width: 30mm; padding-right: 4pt; }
        .ranking .barra { height: 4pt; background: #1a7a46; }
        .ranking .barra-fondo { height: 4pt; background: #eef2ea; }
        .ranking .cantidad { width: 9mm; text-align: right; font-weight: bold; }

        /* Detalle */
        .detalle { table-layout: fixed; }
        .detalle th { background: #0f5c3a; color: #ffffff; font-size: 6.5pt; text-transform: uppercase; letter-spacing: 0.3pt; text-align: left; padding: 5pt 5pt; }
        .detalle td { padding: 4pt 5pt; border-bottom: 0.5pt solid #dde5d6; }
        .detalle tr.par td { background: #f6f9f3; }
        .detalle thead { display: table-header-group; }
        .detalle tr { page-break-inside: avoid; }
        .detalle .linea2 { font-size: 7pt; color: #5c6a60; margin-top: 1pt; }
        .sin-salto { white-space: nowrap; }
    </style>
</head>
<body>
    @php
        // Colores de los grupos (los mismos de la vista en línea) y de cada estado del detalle.
        $colores = [
            'pendiente' => '#f59e0b', 'aprobado' => '#10b981', 'entregado' => '#0ea5e9', 'atrasado' => '#e11d48',
            'devuelto' => '#0f766e', 'novedad' => '#fb923c', 'cerrado' => '#94a3b8',
        ];
        $grupoDe = [
            'pendiente' => 'pendiente', 'aprobado' => 'aprobado', 'entregado' => 'entregado', 'devuelto' => 'devuelto',
            'no_recogido' => 'novedad', 'no_entregado' => 'novedad', 'por_corregir' => 'novedad', 'devolucion_rechazada' => 'novedad',
            'rechazado' => 'cerrado', 'cancelado' => 'cerrado',
        ];
        $total = $indicadores['total'];
        $filtros = $reporte->descripcionFiltros();
    @endphp

    <header>
        <table>
            <tr>
                {{-- Logo con su proporción real (468 × 163 px): ancho y alto fijos, no se deforma --}}
                <td style="width: 50mm; vertical-align: middle;">
                    <img src="{{ public_path('images/logo-uts-horizontal.png') }}" alt="Logo de la institución" style="width: 46mm; height: 16mm;">
                </td>
                <td style="vertical-align: middle; padding-left: 4mm; border-left: 0.75pt solid #dde5d6;">
                    <div class="titulo">Reporte de préstamos</div>
                    <div class="suave" style="margin-top: 1pt;">Sistema de préstamo de equipos y espacios · Caso simulado</div>
                </td>
                <td class="generado" style="width: 62mm; vertical-align: middle;">
                    Generado el {{ $generado->format('d/m/Y') }} a las {{ $generado->format('h:i a') }}<br>
                    por <strong style="color: #0b3d2a;">{{ $reporte->usuario->name }}</strong> ({{ $reporte->usuario->rol }})
                </td>
            </tr>
        </table>
        <div class="franja"></div>
        <div class="linea"></div>
    </header>

    <footer>
        Proyecto Final Integrador · Grupo 5 · Datos de prueba con fines académicos
    </footer>

    {{-- Filtros aplicados --}}
    <table class="filtros">
        <tr>
            @foreach ($filtros as $etiqueta => $valor)
                <td>
                    <div class="etiqueta">{{ $etiqueta }}</div>
                    <div class="valor">{{ $valor }}</div>
                </td>
            @endforeach
            @for ($i = count($filtros); $i < 5; $i++)
                <td></td>
            @endfor
        </tr>
    </table>

    {{-- Indicadores --}}
    <div class="seccion">
        <h2>Indicadores del periodo</h2>
        <table class="indicadores">
            <tr class="rotulos">
                <td class="total">Solicitudes</td>
                @foreach (ReportePrestamos::ESTADOS as $valor => $etiqueta)
                    <td class="{{ $valor === 'atrasado' && $indicadores['estados'][$valor] > 0 ? 'alerta' : '' }}">
                        <span class="punto" style="background: {{ $colores[$valor] }};"></span>{{ $etiqueta }}
                    </td>
                @endforeach
            </tr>
            <tr class="cifras">
                <td class="total">{{ $total }}</td>
                @foreach (ReportePrestamos::ESTADOS as $valor => $etiqueta)
                    <td class="{{ $valor === 'atrasado' && $indicadores['estados'][$valor] > 0 ? 'alerta' : '' }}">{{ $indicadores['estados'][$valor] }}</td>
                @endforeach
            </tr>
            <tr class="porcentajes">
                <td class="total">100 %</td>
                @foreach (ReportePrestamos::ESTADOS as $valor => $etiqueta)
                    <td class="{{ $valor === 'atrasado' && $indicadores['estados'][$valor] > 0 ? 'alerta' : '' }}">{{ $total > 0 ? round($indicadores['estados'][$valor] * 100 / $total) : 0 }} %</td>
                @endforeach
            </tr>
        </table>

        {{-- La misma distribución como barra --}}
        @if ($total > 0)
            <table class="distribucion">
                <tr>
                    @foreach ($indicadores['estados'] as $valor => $cantidad)
                        @if ($cantidad > 0)
                            <td style="width: {{ round($cantidad * 100 / $total, 2) }}%; background: {{ $colores[$valor] }};"></td>
                        @endif
                    @endforeach
                </tr>
            </table>
        @endif
        <p class="pequena suave" style="margin: 4pt 0 0;">
            @if ($indicadores['automatica'] + $indicadores['funcionario'] > 0)
                Aprobación: <strong>{{ $indicadores['automatica'] }}</strong> automáticas y <strong>{{ $indicadores['funcionario'] }}</strong> por un funcionario.
            @endif
            Los indicadores cuentan todos los estados del periodo, aunque se haya filtrado uno.
        </p>
    </div>

    {{-- Rankings, uno al lado del otro --}}
    <table class="seccion">
        <tr>
            @foreach ([['Lo más pedido', $masPedidos], ['Carreras que más solicitan', $carreras]] as [$titulo, $filas])
                <td style="width: 50%; {{ $loop->first ? 'padding-right: 7mm;' : 'padding-left: 7mm;' }}">
                    <h2>{{ $titulo }}</h2>
                    @if ($filas->isEmpty())
                        <p class="suave">Sin datos en este periodo.</p>
                    @else
                        @php($maximo = max(1, $filas->max('total')))
                        <table class="ranking">
                            @foreach ($filas as $fila)
                                @php($ancho = max(3, round($fila->total * 100 / $maximo)))
                                <tr>
                                    <td class="pos">{{ $loop->iteration }}.</td>
                                    <td>
                                        {{ $fila->nombre }}
                                        @isset($fila->codigo)<span class="suave pequena"> · {{ $fila->codigo }}</span>@endisset
                                    </td>
                                    <td class="barra-celda">
                                        <table><tr>
                                            <td class="barra" style="width: {{ $ancho }}%; padding: 0;"></td>
                                            @if ($ancho < 100)
                                                <td class="barra-fondo" style="padding: 0;"></td>
                                            @endif
                                        </tr></table>
                                    </td>
                                    <td class="cantidad">{{ $fila->total }}</td>
                                </tr>
                            @endforeach
                        </table>
                    @endif
                </td>
            @endforeach
        </tr>
    </table>

    {{-- Detalle completo: empieza en una página nueva, así la primera queda como resumen --}}
    <div class="seccion" style="page-break-before: always; margin-top: 0;">
        <h2>Detalle · {{ $prestamos->count() }} {{ $prestamos->count() === 1 ? 'solicitud' : 'solicitudes' }}</h2>
        @if ($prestamos->isEmpty())
            <p class="suave">No hay solicitudes con estos filtros.</p>
        @else
            <table class="detalle">
                <thead>
                    <tr>
                        <th style="width: 9%;">Fecha</th>
                        <th style="width: 17%;">Bloque</th>
                        <th style="width: 21%;">Equipo o espacio</th>
                        <th style="width: 25%;">Solicitante</th>
                        <th style="width: 15%;">Estado</th>
                        <th style="width: 13%;">Atendió</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($prestamos as $prestamo)
                        @php($color = $prestamo->estaAtrasado() ? $colores['atrasado'] : $colores[$grupoDe[$prestamo->estado] ?? 'cerrado'])
                        <tr class="{{ $loop->even ? 'par' : '' }}">
                            <td class="sin-salto">
                                {{ $prestamo->fecha?->format('d/m/Y') }}
                                <div class="linea2">{{ ucfirst($prestamo->fecha?->translatedFormat('l')) }}</div>
                            </td>
                            <td class="sin-salto" style="font-size: 7.5pt;">{{ $prestamo->bloque() }}</td>
                            <td>
                                {{ $prestamo->equipo->nombre }}
                                <div class="linea2">{{ $prestamo->equipo->codigo_inventario }} · {{ $prestamo->equipo->categoria->nombre }}</div>
                            </td>
                            <td>
                                {{ $prestamo->solicitante->name }}
                                <div class="linea2">{{ ucfirst($prestamo->solicitante->rol) }}{{ $prestamo->solicitante->programa ? ' · '.$prestamo->solicitante->programa->nombre : '' }}</div>
                            </td>
                            <td>
                                <span class="punto" style="background: {{ $color }};"></span>
                                <span style="{{ $prestamo->estaAtrasado() ? 'color: #b4202a; font-weight: bold;' : '' }}">{{ ReportePrestamos::nombreEstadoDe($prestamo) }}</span>
                            </td>
                            <td>{{ $prestamo->funcionario?->name ?? ($prestamo->aprobacion === 'automatica' ? 'Automática' : '—') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Número de página «Página X de Y» en el pie de cada página --}}
    <script type="text/php">
        if (isset($pdf)) {
            $fuente = $fontMetrics->getFont('DejaVu Sans');
            $pdf->page_text($pdf->get_width() - 105, $pdf->get_height() - 33, 'Página {PAGE_NUM} de {PAGE_COUNT}', $fuente, 7, [0.36, 0.42, 0.38]);
        }
    </script>
</body>
</html>
