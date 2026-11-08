<?php

namespace App\Imports;

use Illuminate\Validation\ValidationException;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Lee el «Registro de actividades académicas» (LISTADO) que emite el sistema
 * académico de la UEB: cabecera con carrera, docente, asignatura y paralelo, y
 * una fila por estudiante con número, apellidos y nombres, correo y teléfono.
 */
class RosterPdf
{
    /**
     * @return array{career: string|null, teacher: string|null, subject: string, section: string, students: list<array{number: int, name: string, email: string, phone: string|null}>}
     */
    public function read(string $path): array
    {
        try {
            $text = (new Parser)->parseFile($path)->getText();
        } catch (Throwable) {
            throw self::invalid('No se pudo leer el PDF. Sube el listado tal como lo descargas del sistema académico.');
        }

        return self::parseText($text);
    }

    /**
     * @return array{career: string|null, teacher: string|null, subject: string, section: string, students: list<array{number: int, name: string, email: string, phone: string|null}>}
     */
    public static function parseText(string $text): array
    {
        $text = str_replace("\r", '', $text);
        // La cabecera va hasta los títulos de la tabla; los valores largos ocupan dos líneas.
        $header = preg_split('/\n\s*CAP[IÍ]TULO\b/u', $text, 2)[0] ?? '';
        $header = (string) preg_replace('/\b(Fecha|Hora):\s*_*/u', "\n", $header);
        $header = (string) preg_replace('/\s+/u', ' ', $header);

        $field = fn (string $label, string $next): ?string => preg_match('/'.$label.':\s*(.*?)\s*(?:'.$next.':|$)/u', $header, $match) && trim($match[1]) !== ''
            ? trim($match[1])
            : null;

        $subject = $field('Asignatura', 'Paralelo');
        $section = preg_match('/Paralelo:\s*(\S+)/u', $header, $match) ? $match[1] : null;
        if ($subject === null || $section === null) {
            throw self::invalid('El PDF no parece un listado de estudiantes: no se encontró la asignatura o el paralelo.');
        }

        $students = [];
        $seen = [];
        foreach (self::rows($text) as $line) {
            if (! preg_match('/^\s*(\d{1,3})\s+(.+?)\s+([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,})(?:\s*(\d{7,10}))?\s*$/u', $line, $match)) {
                continue;
            }
            $email = mb_strtolower($match[3]);
            if (isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;
            $students[] = [
                'number' => (int) $match[1],
                'name' => (string) preg_replace('/\s+/u', ' ', trim($match[2])),
                'email' => $email,
                'phone' => isset($match[4]) && preg_match('/^09\d{8}$/', $match[4]) ? $match[4] : null,
            ];
        }
        if ($students === []) {
            throw self::invalid('El listado no tiene estudiantes con correo.');
        }

        return [
            'career' => $field('Carrera', 'Docente'),
            'teacher' => $field('Docente', 'Asignatura'),
            'subject' => $subject,
            'section' => $section,
            'students' => $students,
        ];
    }

    /**
     * Líneas de estudiantes. Un correo largo se corta en la celda y su final
     * («c») y el teléfono quedan en las líneas siguientes: se vuelven a unir.
     *
     * @return list<string>
     */
    private static function rows(string $text): array
    {
        $rows = [];
        // Fila a la que aún le puede faltar el final del correo o el teléfono.
        $open = null;
        foreach (explode("\n", $text) as $line) {
            $trimmed = trim($line);
            if (preg_match('/^\d{1,3}\s+\S.*@/u', $trimmed)) {
                if ($open !== null) {
                    $rows[] = $open;
                }
                $open = $trimmed;
            } elseif ($open !== null && ! preg_match('/\d{7,10}$/', $open) && preg_match('/^[A-Za-z.]{1,10}$/', $trimmed)) {
                $open .= $trimmed;
            } elseif ($open !== null && preg_match('/^\d{7,10}$/', $trimmed)) {
                $rows[] = $open.' '.$trimmed;
                $open = null;
            } elseif ($open !== null) {
                $rows[] = $open;
                $open = null;
            }
        }
        if ($open !== null) {
            $rows[] = $open;
        }

        return $rows;
    }

    private static function invalid(string $message): ValidationException
    {
        return ValidationException::withMessages(['file' => $message]);
    }
}
