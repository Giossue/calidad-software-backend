<?php

namespace App\Http\Requests\Concerns;

use App\Rules\PersonNameCount;
use App\Support\PersonName;

/**
 * Nombres y apellidos en dos campos (`first_names`, `last_names`) que el request
 * entrega ya combinados como `name` ("apellidos nombres"), el único campo que
 * guarda el sistema. Uno o dos nombres; dos apellidos por regla general y uno
 * solo como excepción.
 *
 * Quien lo use debe llamar a `preparePersonNames()` desde `prepareForValidation()`
 * y agregar `personNameRules()` a sus reglas.
 */
trait CombinesPersonNames
{
    protected function preparePersonNames(): void
    {
        foreach (['first_names', 'last_names'] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => PersonName::normalize($this->input($field))]);
            }
        }
    }

    /**
     * Con `$required = false` (ediciones) ambos campos son opcionales, pero si
     * llega uno debe llegar el otro.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function personNameRules(bool $required = true): array
    {
        return PersonNameCount::rules($required);
    }

    /** @return array<string, string> */
    protected function personNameAttributes(): array
    {
        return ['first_names' => 'nombres', 'last_names' => 'apellidos'];
    }

    /** @return array<string, string> */
    protected function personNameMessages(): array
    {
        return PersonNameCount::messages();
    }

    /**
     * Los datos validados con `first_names` y `last_names` reemplazados por `name`.
     *
     * @param  array-key|null  $key
     */
    public function validated($key = null, $default = null): mixed
    {
        return data_get(PersonName::combine(parent::validated()), $key, $default);
    }
}
