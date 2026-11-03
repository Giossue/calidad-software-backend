<?php

namespace App\Support;

/**
 * Dominios de correo institucional de la UEB: @ueb.edu.ec y, para algunos
 * estudiantes, @mailes.ueb.edu.ec.
 */
final class InstitutionalEmail
{
    /** @var list<string> */
    public const DOMAINS = ['@ueb.edu.ec', '@mailes.ueb.edu.ec'];

    /** Regla de validación para usar junto a `email`. */
    public static function rule(): string
    {
        return 'ends_with:'.implode(',', self::DOMAINS);
    }

    public static function message(): string
    {
        return 'El correo debe ser institucional ('.implode(' o ', self::DOMAINS).').';
    }
}
