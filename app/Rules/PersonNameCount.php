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

    public const FIRST_NAMES_MESSAGE = 'Ingresa uno o dos nombres.';

    public const LAST_NAMES_MESSAGE = 'Ingresa tus dos apellidos, o uno solo si tu registro civil tiene un único apellido.';

    /**
     * Reglas de `first_names` y `last_names`. Con `$required = false` ambos son
     * opcionales, pero si llega uno debe llegar el otro.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(bool $required = true): array
    {
        $presence = fn (string $other): array => $required ? ['required'] : ['required_with:'.$other, 'filled'];

        return [
            'first_names' => [...$presence('last_names'), 'string', 'max:70', 'regex:/^[\pL\s]+$/u', new self(1, 2, self::FIRST_NAMES_MESSAGE)],
            'last_names' => [...$presence('first_names'), 'string', 'max:70', 'regex:/^[\pL\s]+$/u', new self(1, 2, self::LAST_NAMES_MESSAGE)],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'first_names.regex' => 'Los nombres solo pueden contener letras y espacios.',
            'last_names.regex' => 'Los apellidos solo pueden contener letras y espacios.',
        ];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $count = is_string($value) ? PersonName::count($value) : 0;
        if ($count < $this->min || $count > $this->max) {
            $fail($this->message);
        }
    }
}
