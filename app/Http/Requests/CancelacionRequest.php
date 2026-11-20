<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Motivo con el que el funcionario o el administrador cancela una solicitud aprobada.
 */
class CancelacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('atender', $this->route('prestamo'));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('motivo_cancelacion'))) {
            $this->merge(['motivo_cancelacion' => trim($this->input('motivo_cancelacion'))]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return ['motivo_cancelacion' => ['required', 'string', 'min:5', 'max:500']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo_cancelacion.required' => 'Escribe el motivo de la cancelación: el solicitante lo verá en su notificación.',
            'motivo_cancelacion.min' => 'El motivo debe tener al menos 5 caracteres.',
            'motivo_cancelacion.max' => 'El motivo no puede tener más de 500 caracteres.',
        ];
    }
}
