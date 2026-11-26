<?php

namespace App\Support;

/**
 * Nombres, catálogos, temas y títulos se guardan en MAYÚSCULAS para que la
 * información sea consistente, sin importar cómo se escribió.
 */
final class Uppercase
{
    public static function text(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', $value)), 'UTF-8');
    }
}
