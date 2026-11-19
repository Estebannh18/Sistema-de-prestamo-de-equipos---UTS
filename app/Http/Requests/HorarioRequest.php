<?php

namespace App\Http\Requests;

use App\Models\Horario;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validaciones de los horarios de un espacio o equipo.
 * Llegan como bloques[día][n][inicio|fin], con el día de 1 (lunes) a 6 (sábado).
 */
class HorarioRequest extends FormRequest
{
    /** Bloques máximos por día, para que el formulario no crezca sin control. */
    public const MAX_POR_DIA = 15;

    public function authorize(): bool
    {
        return (bool) $this->user()?->esAdministrador();
    }

    /**
     * Quita las filas vacías (por ejemplo, un bloque agregado y dejado en blanco).
     */
    protected function prepareForValidation(): void
    {
        $bloques = $this->input('bloques');
        if (! is_array($bloques)) {
            return;
        }

        $limpios = [];
        foreach ($bloques as $dia => $filas) {
            $filas = array_values(array_filter((array) $filas, fn ($fila) => is_array($fila) && (filled($fila['inicio'] ?? null) || filled($fila['fin'] ?? null))));
            if ($filas !== []) {
                $limpios[$dia] = $filas;
            }
        }
        $this->merge(['bloques' => $limpios]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'bloques' => ['nullable', 'array'],
            'bloques.*' => ['array', 'max:'.self::MAX_POR_DIA],
            'bloques.*.*.inicio' => ['required', 'date_format:H:i'],
            'bloques.*.*.fin' => ['required', 'date_format:H:i', 'after:bloques.*.*.inicio'],
        ];
    }

    /**
     * Días válidos y bloques que no se crucen en el mismo día.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            foreach ((array) $this->input('bloques', []) as $dia => $filas) {
                if (! array_key_exists((int) $dia, Horario::DIAS) || (string) (int) $dia !== (string) $dia) {
                    $validator->errors()->add('bloques', 'Los horarios solo se configuran de lunes a sábado.');

                    continue;
                }
                if ($validator->errors()->has("bloques.{$dia}.*")) {
                    continue;
                }

                $ordenados = collect($filas)->sortBy('inicio')->values();
                for ($i = 1; $i < $ordenados->count(); $i++) {
                    $anterior = $ordenados[$i - 1];
                    $actual = $ordenados[$i];
                    if ($actual['inicio'] < $anterior['fin']) {
                        // Las horas terminan en «a. m.» o «p. m.»: el punto final ya está ahí.
                        $validator->errors()->add("bloques.{$dia}", sprintf(
                            'El %s, el bloque %s se cruza con %s',
                            mb_strtolower(Horario::DIAS[(int) $dia]),
                            Horario::rango($actual['inicio'], $actual['fin']),
                            Horario::rango($anterior['inicio'], $anterior['fin']),
                        ));
                    }
                }
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bloques.*.max' => 'Cada día puede tener máximo '.self::MAX_POR_DIA.' bloques.',
            'bloques.*.*.inicio.required' => 'Falta la hora de inicio de un bloque.',
            'bloques.*.*.inicio.date_format' => 'Una hora de inicio no es válida. Usa el formato de 24 horas, por ejemplo 07:30.',
            'bloques.*.*.fin.required' => 'Falta la hora de fin de un bloque.',
            'bloques.*.*.fin.date_format' => 'Una hora de fin no es válida. Usa el formato de 24 horas, por ejemplo 09:00.',
            'bloques.*.*.fin.after' => 'En cada bloque, la hora de fin debe ser después de la hora de inicio.',
        ];
    }
}
