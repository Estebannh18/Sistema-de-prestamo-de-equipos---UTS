<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfiguracionRequest;
use App\Models\Configuracion;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Módulo Configuración del administrador.
 */
class ConfiguracionController extends Controller
{
    public function edit(): View
    {
        return view('configuracion.edit', ['valores' => Configuracion::todos()]);
    }

    public function update(ConfiguracionRequest $request): RedirectResponse
    {
        foreach ($request->validated() as $clave => $valor) {
            Configuracion::updateOrCreate(['clave' => $clave], ['valor' => (int) $valor]);
        }

        return redirect()->route('configuracion.edit')->with('status', 'Configuración guardada. Los cambios se aplican desde ya a las nuevas solicitudes.');
    }
}
