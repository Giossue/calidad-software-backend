<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Acepta una cédula ecuatoriana válida (10 dígitos) o un pasaporte extranjero
 * de 6 a 9 caracteres alfanuméricos en mayúsculas.
 */
class CedulaOPasaporte implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && preg_match('/^\d{10}$/', $value)) {
            (new CedulaEcuatoriana)->validate($attribute, $value, $fail);

            return;
        }

        if (! is_string($value) || ! preg_match('/^[A-Z0-9]{6,9}$/', $value)) {
            $fail('El campo :attribute debe ser una cédula de 10 dígitos o un pasaporte de 6 a 9 letras mayúsculas o números.');
        }
    }
}
