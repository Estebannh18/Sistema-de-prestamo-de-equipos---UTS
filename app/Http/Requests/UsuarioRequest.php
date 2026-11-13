<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validaciones del módulo Usuarios (crear y editar).
 * Las decisiones están explicadas en infoDesarrollo/modulo-usuarios-validaciones.html.
 */
class UsuarioRequest extends FormRequest
{
    /** Roles que deben elegir su programa académico de la lista. Los demás pueden escribir su dependencia. */
    public const ROLES_CON_PROGRAMA = ['estudiante', 'docente'];

    /** Formato del número según el tipo de documento: [patrón, mensaje si no cumple]. */
    public const FORMATOS_DOCUMENTO = [
        'CC' => ['/^\d{6,10}$/', 'La cédula de ciudadanía solo tiene números, entre 6 y 10 dígitos.'],
        'TI' => ['/^\d{10,11}$/', 'La tarjeta de identidad solo tiene números, de 10 u 11 dígitos.'],
        'CE' => ['/^\d{6,10}$/', 'La cédula de extranjería solo tiene números, entre 6 y 10 dígitos.'],
        'PA' => ['/^[A-Z0-9]{6,12}$/', 'El pasaporte tiene entre 6 y 12 letras o números, sin espacios ni símbolos.'],
    ];

    /**
     * Solo el administrador gestiona usuarios. La ruta ya lo exige con el middleware rol;
     * esta comprobación es una segunda barrera.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->esAdministrador();
    }

    /**
     * Limpia los datos antes de validarlos, para que el mismo dato escrito de dos formas
     * cuente como uno solo: "1.098.765.432" y "1098765432" son el mismo documento.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => self::normalizarTexto($this->input('name')),
            'email' => self::normalizarCorreo($this->input('email')),
            'tipo_documento' => is_string($this->input('tipo_documento')) ? Str::upper(trim($this->input('tipo_documento'))) : $this->input('tipo_documento'),
            'documento' => self::quitarSeparadores($this->input('documento'), ['.', ',', ' ', '-'], mayusculas: true),
            'telefono' => self::quitarSeparadores($this->input('telefono'), [' ', '-', '(', ')', '.']),
            'dependencia' => self::normalizarTexto($this->input('dependencia')),
        ]);

        // Cada rol guarda solo su dato: estudiantes y docentes, el programa; el personal, la dependencia.
        // Así no queda un programa viejo pegado a alguien que pasó a ser funcionario, ni al revés.
        if (in_array($this->input('rol'), self::ROLES_CON_PROGRAMA, true)) {
            $this->merge(['dependencia' => null]);
        } else {
            $this->merge(['programa_id' => null]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $usuario = $this->usuarioEditado();

        return [
            'name' => self::reglasNombre(),
            'email' => self::reglasCorreo($usuario),
            'tipo_documento' => ['required', Rule::in(array_keys(User::TIPOS_DOCUMENTO))],
            // El número no se repite aunque cambie el tipo: la tarjeta de identidad y la cédula de una persona tienen el mismo número.
            'documento' => ['bail', 'required', 'string', 'max:20', function (string $atributo, mixed $valor, \Closure $falla) {
                $formato = self::FORMATOS_DOCUMENTO[$this->input('tipo_documento')] ?? null;
                if ($formato && ! preg_match($formato[0], (string) $valor)) {
                    $falla($formato[1]);
                }
            }, Rule::unique(User::class)->ignore($usuario)],
            'telefono' => ['nullable', 'digits_between:7,10'],
            'rol' => ['required', Rule::in(User::ROLES)],
            'programa_id' => [
                Rule::requiredIf(fn () => in_array($this->input('rol'), self::ROLES_CON_PROGRAMA, true)),
                'nullable', 'integer', Rule::exists('programas', 'id'),
            ],
            'dependencia' => ['nullable', 'string', 'min:3', 'max:150'],
            'password' => [
                $usuario ? 'nullable' : 'required',
                'string',
                Password::defaults(),
                'confirmed',
                'different:documento',
            ],
        ];
    }

    /**
     * Reglas que dependen de otros datos del sistema, no solo del formulario.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $usuario = $this->usuarioEditado();
            $rolNuevo = $this->input('rol');

            // Solo los estudiantes pueden ser menores de edad; los demás roles usan documento de adulto.
            if ($this->input('tipo_documento') === 'TI' && in_array($rolNuevo, User::ROLES, true) && $rolNuevo !== 'estudiante'
                && ! $validator->errors()->has('tipo_documento')) {
                $validator->errors()->add('tipo_documento', 'La tarjeta de identidad solo se acepta para estudiantes.');
            }

            if (! $usuario || $validator->errors()->has('rol') || $rolNuevo === $usuario->rol) {
                return;
            }

            if ($usuario->is($this->user())) {
                $validator->errors()->add('rol', 'No puedes cambiar tu propio rol. Pídeselo a otro administrador.');

                return;
            }

            if ($usuario->esAdministrador() && $usuario->activo && User::administradoresActivos() === 1) {
                $validator->errors()->add('rol', 'Debe quedar al menos un administrador activo en el sistema.');

                return;
            }

            if ($usuario->tienePrestamosActivos()) {
                $validator->errors()->add('rol', 'No se puede cambiar el rol: el usuario tiene préstamos pendientes o sin devolver.');
            }
        }];
    }

    /**
     * Mensajes propios, en lenguaje sencillo.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::mensajesNombreYCorreo() + [
            'tipo_documento.required' => 'Elige el tipo de documento.',
            'tipo_documento.in' => 'El tipo de documento debe ser cédula de ciudadanía, tarjeta de identidad, cédula de extranjería o pasaporte.',
            'documento.required' => 'El número de documento es obligatorio.',
            'documento.max' => 'El número de documento es demasiado largo.',
            'documento.unique' => 'Ya existe un usuario con ese número de documento.',
            'telefono.digits_between' => 'El teléfono solo puede tener números, entre 7 y 10 dígitos.',
            'rol.required' => 'Elige un rol.',
            'rol.in' => 'El rol debe ser administrador, funcionario, docente o estudiante.',
            'programa_id.required' => 'Elige el programa académico: es obligatorio para estudiantes y docentes.',
            'programa_id.integer' => 'Elige un programa de la lista.',
            'programa_id.exists' => 'Elige un programa de la lista.',
            'dependencia.min' => 'La dependencia debe tener al menos 3 caracteres.',
            'dependencia.max' => 'La dependencia no puede tener más de 150 caracteres.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.different' => 'La contraseña no puede ser igual al número de documento.',
        ];
    }

    /** Usuario que se está editando, o null si se está creando. */
    private function usuarioEditado(): ?User
    {
        $usuario = $this->route('usuario');

        return $usuario instanceof User ? $usuario : null;
    }

    // ---------- Reglas compartidas con el perfil de cada usuario ----------

    /**
     * Nombre y apellido: solo letras (con tildes y ñ), espacios, apóstrofos y guiones.
     *
     * @return array<int, string>
     */
    public static function reglasNombre(): array
    {
        // bail: muestra solo el primer error, para no repetir dos mensajes sobre lo mismo.
        return ['bail', 'required', 'string', 'max:100', "regex:/^[\\pL'\\-]+( [\\pL'\\-]+)+$/u", 'min:5'];
    }

    /**
     * Correo con formato válido, en minúsculas y sin repetirse entre usuarios.
     *
     * @return array<int, mixed>
     */
    public static function reglasCorreo(?User $ignorar = null): array
    {
        return ['required', 'string', 'max:255', 'email:filter', 'regex:/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/', Rule::unique(User::class)->ignore($ignorar)];
    }

    /**
     * @return array<string, string>
     */
    public static function mensajesNombreYCorreo(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.min' => 'El nombre debe tener al menos 5 caracteres.',
            'name.max' => 'El nombre no puede tener más de 100 caracteres.',
            'name.regex' => 'Escribe nombre y apellido, solo con letras.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'Escribe un correo válido, por ejemplo nombre@uts.edu.co.',
            'email.regex' => 'Escribe un correo válido, por ejemplo nombre@uts.edu.co.',
            'email.max' => 'El correo no puede tener más de 255 caracteres.',
            'email.unique' => 'Ya existe un usuario con ese correo.',
        ];
    }

    /** Quita espacios al inicio y al final, y deja uno solo entre palabras. */
    public static function normalizarTexto(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return $valor;
        }

        $limpio = Str::squish($valor);

        return $limpio === '' ? null : $limpio;
    }

    /** Quita espacios y pasa el correo a minúsculas. */
    public static function normalizarCorreo(mixed $valor): ?string
    {
        return is_string($valor) ? Str::lower(trim($valor)) : $valor;
    }

    /**
     * Quita los separadores que la gente suele escribir en números (puntos, espacios, guiones).
     * Con $mayusculas, también pasa las letras a mayúsculas (pasaportes).
     *
     * @param  array<int, string>  $separadores
     */
    private static function quitarSeparadores(mixed $valor, array $separadores, bool $mayusculas = false): ?string
    {
        if (! is_string($valor)) {
            return $valor;
        }

        $limpio = str_replace($separadores, '', trim($valor));
        if ($mayusculas) {
            $limpio = Str::upper($limpio);
        }

        return $limpio === '' ? null : $limpio;
    }
}
