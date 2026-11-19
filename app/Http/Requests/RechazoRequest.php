<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Al rechazar una solicitud, el motivo es obligatorio: el solicitante lo ve en su notificación.
 */
class RechazoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('atender', $this->route('prestamo'));
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
        return ['motivo' => ['required', 'string', 'min:5', 'max:500']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Escribe el motivo del rechazo: el estudiante lo verá en su notificación.',
            'motivo.min' => 'El motivo debe tener al menos 5 caracteres.',
            'motivo.max' => 'El motivo no puede tener más de 500 caracteres.',
        ];
    }
}
