<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class SeguridadController extends Controller
{
    /**
     * Muestra la pestaña 2FA, donde cada usuario decide si la activa.
     */
    public function show(Request $request): View
    {
        return view('seguridad.2fa', ['user' => $request->user()]);
    }
}
