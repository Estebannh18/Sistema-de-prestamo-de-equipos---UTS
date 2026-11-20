<?php

namespace App\Http\Requests;

use App\Models\Devolucion;
use App\Models\PrestamoFoto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Formulario de una revisión de la devolución. Todo se escribe a mano. El resultado decide qué más
 * se pide: motivo para «por corregir» y «rechazada», plazo opcional para «por corregir», y la
 * descripción del pendiente si se acepta con un pendiente.
 * Las rutas ya exigen rol de administrador o funcionario.
 */
class DevolucionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['complementos', 'faltantes', 'observaciones', 'motivo', 'plazo', 'pendiente'] as $campo) {
            if (is_string($this->input($campo))) {
                $this->merge([$campo => trim($this->input($campo))]);
            }
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::in(array_keys(Devolucion::ESTADOS))],
            'complementos' => ['required', 'string', 'min:3', 'max:2000'],
            'complementos_completos' => ['required', 'boolean'],
            'faltantes' => ['nullable', 'required_if:complementos_completos,0', 'string', 'max:2000'],
            'condiciones_cumplidas' => ['nullable', 'boolean'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'resultado' => ['required', Rule::in(array_keys(Devolucion::RESULTADOS))],
            'motivo' => ['nullable', 'required_unless:resultado,aceptada', 'string', 'min:5', 'max:500'],
            'plazo' => ['nullable', 'string', 'max:100'],
            'con_pendiente' => ['nullable', 'boolean'],
            'pendiente' => ['nullable', 'string', 'max:500'],
        ] + PrestamoFoto::reglas();
    }

    /**
     * Las condiciones se preguntan solo si se escribieron en la entrega; el pendiente, solo si se acepta con pendiente.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $entrega = $this->route('prestamo')?->entrega;
            if ($entrega && filled($entrega->condiciones) && $this->input('condiciones_cumplidas') === null) {
                $validator->errors()->add('condiciones_cumplidas', 'Indica si cumplió las condiciones que se escribieron en la entrega.');
            }
            if ($this->input('resultado') === 'aceptada' && $this->boolean('con_pendiente') && blank($this->input('pendiente'))) {
                $validator->errors()->add('pendiente', 'Describe qué quedó pendiente (por ejemplo: falta una pata del video beam).');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.required' => 'Elige el estado en que se recibe.',
            'estado.in' => 'Elige el estado en que se recibe.',
            'complementos.required' => 'Escribe los complementos que devuelve.',
            'complementos.min' => 'Escribe los complementos que devuelve.',
            'complementos.max' => 'Los complementos no pueden tener más de 2000 caracteres.',
            'complementos_completos.required' => 'Indica si devolvió todos los complementos.',
            'complementos_completos.boolean' => 'Indica si devolvió todos los complementos.',
            'faltantes.required_if' => 'Escribe qué complementos faltan (por ejemplo: se entregaron 80 sillas y se devolvieron 79).',
            'faltantes.max' => 'Los faltantes no pueden tener más de 2000 caracteres.',
            'observaciones.max' => 'Las observaciones no pueden tener más de 2000 caracteres.',
            'resultado.required' => 'Elige el resultado: aceptada, por corregir o rechazada.',
            'resultado.in' => 'Elige el resultado: aceptada, por corregir o rechazada.',
            'motivo.required_unless' => 'Escribe el motivo: el solicitante lo verá en su notificación.',
            'motivo.min' => 'El motivo debe tener al menos 5 caracteres.',
            'motivo.max' => 'El motivo no puede tener más de 500 caracteres.',
            'plazo.max' => 'El plazo no puede tener más de 100 caracteres.',
            'pendiente.max' => 'El pendiente no puede tener más de 500 caracteres.',
        ] + PrestamoFoto::mensajes();
    }
}
