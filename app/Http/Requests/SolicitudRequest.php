<?php

namespace App\Http\Requests;

use App\Models\Equipo;
use App\Models\Horario;
use App\Models\Prestamo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Datos de una solicitud: un solo espacio o equipo, una fecha y un bloque de su horario.
 * Las reglas de negocio (disponibilidad, carrera, máximos…) las revisa PrestamoService.
 */
class SolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('solicitar', Prestamo::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'equipo_id' => ['required', 'integer', Rule::exists(Equipo::class, 'id')],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'horario_id' => ['required', 'integer', Rule::exists(Horario::class, 'id')],
            'confirmado' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'equipo_id.required' => 'Elige qué quieres solicitar.',
            'equipo_id.exists' => 'Ese espacio o equipo no existe.',
            'fecha.required' => 'Elige el día.',
            'fecha.date_format' => 'El día no es válido.',
            'horario_id.required' => 'Elige un bloque de horas.',
            'horario_id.exists' => 'Ese bloque ya no existe. Elige otro.',
        ];
    }
}
