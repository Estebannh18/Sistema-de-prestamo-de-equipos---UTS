<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Datos de un mantenimiento programado: desde (hoy o después), hasta (opcional) y motivo.
 * Las rutas ya exigen rol de administrador o funcionario.
 */
class MantenimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('motivo'))) {
            $this->merge(['motivo' => trim($this->input('motivo'))]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'desde' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'desde.required' => 'Elige desde qué día estará en mantenimiento.',
            'desde.date_format' => 'La fecha de inicio no es válida.',
            'desde.after_or_equal' => 'El mantenimiento no puede empezar antes de hoy.',
            'hasta.date_format' => 'La fecha de fin no es válida.',
            'hasta.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la de inicio.',
            'motivo.required' => 'Escribe el motivo: lo verán quienes tengan solicitudes en esas fechas.',
            'motivo.min' => 'El motivo debe tener al menos 5 caracteres.',
            'motivo.max' => 'El motivo no puede tener más de 500 caracteres.',
        ];
    }
}
