<?php

namespace App\Support;

/**
 * Contraseñas provisionales que se envían por correo. Solo usan caracteres que
 * el Markdown del correo no altera (nada de * _ \ [ ] < > `) y que no se
 * confunden al leerlos (sin I l 1 O o 0).
 */
class ProvisionalPassword
{
    private const UPPERCASE = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const LOWERCASE = 'abcdefghijkmnpqrstuvwxyz';

    private const DIGITS = '23456789';

    private const SYMBOLS = '@#$%=?!';

    public static function generate(int $length = 16): string
    {
        $sets = [self::UPPERCASE, self::LOWERCASE, self::DIGITS, self::SYMBOLS];
        $all = implode('', $sets);

        // Al menos un carácter de cada grupo; el resto, de cualquiera.
        $characters = array_map(fn (string $set): string => self::pick($set), $sets);
        while (count($characters) < $length) {
            $characters[] = self::pick($all);
        }

        for ($i = count($characters) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$characters[$i], $characters[$j]] = [$characters[$j], $characters[$i]];
        }

        return implode('', $characters);
    }

    private static function pick(string $set): string
    {
        return $set[random_int(0, strlen($set) - 1)];
    }
}
