<?php

namespace App\Support;

/**
 * Nombres de personas. El sistema guarda un solo campo con apellidos y nombres,
 * en ese orden, aunque se registren por separado.
 */
class PersonName
{
    /** Partículas que se escriben dentro de un nombre o apellido compuesto ("De la Torre", "María de los Ángeles"). */
    private const PARTICLES = ['de', 'del', 'la', 'las', 'los', 'lo', 'y', 'e', 'da', 'das', 'do', 'dos', 'di', 'du', 'van', 'von', 'der', 'den', 'san', 'santa', 'mac', 'mc'];

    /** Recorta y colapsa los espacios repetidos. */
    public static function normalize(?string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }

    /** Apellidos seguidos de nombres, o `null` si ambos están vacíos. */
    public static function full(?string $lastNames, ?string $firstNames): ?string
    {
        $name = self::normalize(self::normalize($lastNames).' '.self::normalize($firstNames));

        return $name === '' ? null : $name;
    }

    /**
     * Reemplaza `first_names` y `last_names` por `name` (apellidos y nombres)
     * cuando llegan; si no, devuelve los datos sin cambios.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function combine(array $data): array
    {
        if (! array_key_exists('first_names', $data) && ! array_key_exists('last_names', $data)) {
            return $data;
        }

        $data['name'] = self::full($data['last_names'] ?? null, $data['first_names'] ?? null);
        unset($data['first_names'], $data['last_names']);

        return $data;
    }

    /**
     * Cuántos nombres (o apellidos) contiene el texto. Las partículas forman parte
     * del nombre que acompañan y no cuentan por separado.
     */
    public static function count(string $value): int
    {
        $words = array_filter(explode(' ', self::normalize($value)), fn (string $word): bool => $word !== '');
        $names = array_filter($words, fn (string $word): bool => ! in_array(mb_strtolower($word), self::PARTICLES, true));

        return $names === [] ? count($words) : count($names);
    }
}
