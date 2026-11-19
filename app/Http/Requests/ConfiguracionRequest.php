<?php

namespace App\Http\Requests;

use App\Models\Configuracion;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validaciones del módulo Configuración: cada valor es un número entero dentro de su rango.
 */
class ConfiguracionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->esAdministrador();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return collect(Configuracion::VALORES)
            ->mapWithKeys(fn ($d, $clave) => [$clave => ['required', 'integer', "min:{$d[4]}", "max:{$d[5]}"]])
            ->all();
    }

    /**
     * Mensajes en español con el nombre de cada valor.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $mensajes = [];
        foreach (Configuracion::VALORES as $clave => [$grupo, $etiqueta, $unidad, , $min, $max]) {
            $nombre = Configuracion::GRUPOS[$grupo][0].' · '.$etiqueta;
            $rango = $unidad === 'minutos' ? "entre {$min} y {$max} minutos" : "entre {$min} y {$max}";
            $mensajes["{$clave}.required"] = "Escribe el valor de «{$nombre}».";
            $mensajes["{$clave}.integer"] = "«{$nombre}» debe ser un número entero.";
            $mensajes["{$clave}.min"] = "«{$nombre}» debe estar {$rango}.";
            $mensajes["{$clave}.max"] = "«{$nombre}» debe estar {$rango}.";
        }

        return $mensajes;
    }
}
