<?php

namespace App\Rules;

use App\Support\PersonName;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Limita cuántos nombres (o apellidos) se escriben en un campo; las partículas no cuentan aparte. */
class PersonNameCount implements ValidationRule
{
    public function __construct(
        private readonly int $min,
        private readonly int $max,
        private readonly string $message,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $count = is_string($value) ? PersonName::count($value) : 0;
        if ($count < $this->min || $count > $this->max) {
            $fail($this->message);
        }
    }
}
