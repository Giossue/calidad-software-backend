<?php

namespace App\Http\Requests\Concerns;

use App\Support\Uppercase;

/**
 * Pasa a MAYÚSCULAS los campos de `uppercaseFields()` antes de validar, para que
 * las reglas de duplicados comparen lo mismo que se guarda.
 */
trait UppercasesInput
{
    /** @return list<string> */
    abstract protected function uppercaseFields(): array;

    /** @return array<string, mixed> */
    public function validationData(): array
    {
        foreach ($this->uppercaseFields() as $field) {
            if ($this->has($field)) {
                $this->merge([$field => Uppercase::text($this->input($field))]);
            }
        }

        return $this->all();
    }
}
