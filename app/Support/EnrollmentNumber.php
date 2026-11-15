<?php

namespace App\Support;

/**
 * Número de matrícula de una inscripción en tutoría: 1.ª, 2.ª o 3.ª. Desde la
 * 2.ª matrícula la asistencia a la tutoría es obligatoria.
 */
final class EnrollmentNumber
{
    public const FIRST = 1;

    public const MAX = 3;

    public const MESSAGE = 'La matrícula debe ser 1.ª, 2.ª o 3.ª.';

    public const ATTRIBUTE = 'matrícula';

    /** Formas aceptadas en la columna `matricula` del listado, ya en minúsculas y sin espacios. */
    private const SPELLINGS = [
        '1' => 1, '1.ª' => 1, '1ª' => 1, '1.a' => 1, 'primera' => 1,
        '2' => 2, '2.ª' => 2, '2ª' => 2, '2.a' => 2, 'segunda' => 2,
        '3' => 3, '3.ª' => 3, '3ª' => 3, '3.a' => 3, 'tercera' => 3,
    ];

    /**
     * Reglas del campo opcional `enrollment_number`.
     *
     * @return list<string>
     */
    public static function rules(): array
    {
        return ['sometimes', 'integer', 'between:1,'.self::MAX];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'enrollment_number.required' => self::MESSAGE,
            'enrollment_number.integer' => self::MESSAGE,
            'enrollment_number.between' => self::MESSAGE,
        ];
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return ['enrollment_number' => self::ATTRIBUTE];
    }

    public static function requiresAttendance(int $number): bool
    {
        return $number >= 2;
    }

    /** Valor de la columna `matricula` del listado: vacía es 1.ª; null si no se reconoce. */
    public static function parse(?string $value): ?int
    {
        $key = mb_strtolower((string) preg_replace('/\s+/u', '', (string) $value));

        return $key === '' ? self::FIRST : (self::SPELLINGS[$key] ?? null);
    }
}
