<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Perfil propio: cada usuario cambia su nombre y su correo con las mismas reglas
 * que usa el administrador. El rol y el documento solo los cambia el administrador.
 */
class ProfileUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => UsuarioRequest::normalizarTexto($this->input('name')),
            'email' => UsuarioRequest::normalizarCorreo($this->input('email')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => UsuarioRequest::reglasNombre(),
            'email' => UsuarioRequest::reglasCorreo($this->user()),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return UsuarioRequest::mensajesNombreYCorreo();
    }
}
