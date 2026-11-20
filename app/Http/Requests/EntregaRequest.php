<?php

namespace App\Http\Requests;

use App\Models\Entrega;
use App\Models\PrestamoFoto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Formulario de entrega. Todo se escribe en cada entrega (nada viene prellenado), y siempre se
 * entrega al solicitante en persona: el funcionario confirma que lo verificó.
 * Las rutas ya exigen rol de administrador o funcionario.
 */
class EntregaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['complementos', 'condiciones', 'observaciones'] as $campo) {
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
            'verificado' => ['accepted'],
            'estado' => ['required', Rule::in(array_keys(Entrega::ESTADOS))],
            'complementos' => ['required', 'string', 'min:3', 'max:2000'],
            'condiciones' => ['nullable', 'string', 'max:2000'],
            'observaciones' => ['nullable', 'required_if:estado,con_observaciones', 'string', 'max:2000'],
        ] + PrestamoFoto::reglas();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'verificado.accepted' => 'Confirma que verificaste que quien recibe es el solicitante: solo él o ella puede recogerlo.',
            'estado.required' => 'Elige el estado en que se entrega.',
            'estado.in' => 'Elige el estado en que se entrega.',
            'complementos.required' => 'Escribe los complementos que se entregan (por ejemplo: tapa del lente, 2 patas, cable de poder).',
            'complementos.min' => 'Escribe los complementos que se entregan.',
            'complementos.max' => 'Los complementos no pueden tener más de 2000 caracteres.',
            'condiciones.max' => 'Las condiciones no pueden tener más de 2000 caracteres.',
            'observaciones.required_if' => 'Escribe las observaciones: elegiste «Con observaciones».',
            'observaciones.max' => 'Las observaciones no pueden tener más de 2000 caracteres.',
        ] + PrestamoFoto::mensajes();
    }
}
