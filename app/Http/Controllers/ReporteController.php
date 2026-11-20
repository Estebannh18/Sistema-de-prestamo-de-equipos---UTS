<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Reportes\ReportePrestamos;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Reporte de préstamos para el administrador y el funcionario (RF-14).
 * El acceso lo limita el middleware de rol de la ruta.
 */
class ReporteController extends Controller
{
    /**
     * Reporte en línea: filtros, indicadores, gráficas, rankings y detalle paginado.
     */
    public function index(Request $request): View
    {
        $reporte = ReportePrestamos::desdePeticion($request);

        return view('reportes.prestamos', [
            'reporte' => $reporte,
            'indicadores' => $reporte->indicadores(),
            'serie' => $reporte->porPeriodo(),
            'masPedidos' => $reporte->masPedidos(),
            'carreras' => $reporte->carrerasQueMasPiden(),
            'prestamos' => $reporte->paginado(),
            'categorias' => Categoria::when($reporte->tipo, fn ($query) => $query->where('tipo', $reporte->tipo))->orderBy('nombre')->get(),
        ]);
    }

    /**
     * Exporta a PDF lo mismo que se ve en línea, con los mismos filtros y el detalle completo.
     */
    public function pdf(Request $request): Response
    {
        $reporte = ReportePrestamos::desdePeticion($request);

        $pdf = Pdf::loadView('reportes.pdf', [
            'reporte' => $reporte,
            'indicadores' => $reporte->indicadores(),
            'masPedidos' => $reporte->masPedidos(),
            'carreras' => $reporte->carrerasQueMasPiden(),
            'prestamos' => $reporte->todos(),
            'generado' => now(),
        ])->setPaper('letter', 'landscape')
            // Permite el script de la propia plantilla que escribe «Página X de Y».
            ->setOption('isPhpEnabled', true);

        $nombre = 'reporte-prestamos-'.$reporte->desde->format('Ymd').'-'.$reporte->hasta->format('Ymd').'.pdf';

        return $pdf->download($nombre);
    }
}
