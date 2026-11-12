<?php

namespace App\Http\Controllers;

class ReporteController extends Controller
{
    /**
     * Reporte de préstamos en línea.
     */
    public function index()
    {
        return view('reportes.prestamos');
    }

    /**
     * Exporta el reporte a PDF.
     */
    public function pdf()
    {
        abort(501, 'Pendiente: se implementa en la fase de este módulo.');
    }
}
