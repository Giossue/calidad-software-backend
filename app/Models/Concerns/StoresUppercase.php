<?php

namespace App\Models\Concerns;

use App\Support\Uppercase;

/**
 * Guarda en MAYÚSCULAS los atributos de `$uppercase`, entren por un formulario,
 * una importación o un seeder.
 *
 * @property-read list<string> $uppercase
 */
trait StoresUppercase
{
    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->uppercase, true)) {
            $value = Uppercase::text($value);
        }

        return parent::setAttribute($key, $value);
    }
}
