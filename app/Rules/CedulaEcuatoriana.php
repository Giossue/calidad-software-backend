<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CedulaEcuatoriana implements ValidationRule
{
    private const COEFFICIENTS = [2, 1, 2, 1, 2, 1, 2, 1, 2];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\d{10}$/', $value)) {
            $fail('El campo :attribute debe tener 10 dígitos numéricos.');

            return;
        }

        $province = (int) substr($value, 0, 2);
        if ($province < 1 || ($province > 24 && $province !== 30)) {
            $fail('El campo :attribute no corresponde a una cédula ecuatoriana válida.');

            return;
        }

        if ((int) $value[2] > 6) {
            $fail('El campo :attribute no corresponde a una cédula ecuatoriana válida.');

            return;
        }

        $digits = array_map('intval', str_split(substr($value, 0, 9)));

        if (self::checkDigit($digits) !== (int) $value[9]) {
            $fail('El campo :attribute no corresponde a una cédula ecuatoriana válida.');
        }
    }

    /** @param array<int, int> $firstNineDigits */
    public static function checkDigit(array $firstNineDigits): int
    {
        $sum = 0;
        foreach (self::COEFFICIENTS as $position => $coefficient) {
            $digit = $firstNineDigits[$position] * $coefficient;
            $sum += $digit >= 10 ? $digit - 9 : $digit;
        }

        return (10 - ($sum % 10)) % 10;
    }
}
