<?php

namespace App\Imports;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CsvReader
{
    public const MAX_ROWS = 1000;

    /**
     * Lee el CSV y devuelve las filas con su número de línea. Acepta separador
     * coma o punto y coma, UTF-8 (con o sin BOM) y Windows-1252. Las líneas cuya
     * primera celda empieza con `#` son comentarios (las instrucciones de la
     * plantilla) y se ignoran; los números de línea siguen siendo los del archivo.
     *
     * @param  array<string, bool>  $columns
     * @return array<int, array{line: int, values: array<string, string|null>}>
     */
    public function read(string $contents, array $columns): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? '';
        if (! mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        }

        $firstLine = self::firstContentLine($contents);
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $stream = self::memoryStream();
        fwrite($stream, $contents);
        rewind($stream);

        $line = 0;
        do {
            $header = fgetcsv($stream, null, $delimiter, '"', '');
            $line++;
        } while (is_array($header) && self::isComment($header));

        if (! is_array($header) || $header === [null]) {
            throw ValidationException::withMessages(['file' => 'El archivo CSV está vacío.']);
        }

        $header = array_map(fn (?string $name): string => self::normalizeHeader((string) $name), $header);
        $missing = array_keys(array_filter($columns, fn (bool $required, string $name): bool => $required && ! in_array($name, $header, true), ARRAY_FILTER_USE_BOTH));
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'file' => 'Faltan columnas obligatorias en el CSV: '.implode(', ', $missing).'.',
            ]);
        }

        $rows = [];
        while (($values = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $line++;
            if (self::isComment($values)) {
                continue;
            }

            $values = array_map(fn (?string $value): string => trim((string) $value), $values);
            if (implode('', $values) === '') {
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                throw ValidationException::withMessages([
                    'file' => 'El archivo supera el máximo de '.self::MAX_ROWS.' filas. Divídelo en varios archivos.',
                ]);
            }

            $row = [];
            foreach (array_keys($columns) as $name) {
                $index = array_search($name, $header, true);
                $value = $index === false ? '' : ($values[$index] ?? '');
                $row[$name] = $value === '' ? null : $value;
            }
            $rows[] = ['line' => $line, 'values' => $row];
        }
        fclose($stream);

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'El archivo CSV no contiene filas para importar.']);
        }

        return $rows;
    }

    /** Primera línea que no es comentario ni está vacía: de ella se deduce el separador. */
    private static function firstContentLine(string $contents): string
    {
        foreach (preg_split('/\r\n|\r|\n/', $contents) ?: [] as $line) {
            $trimmed = ltrim($line, " \t\"");
            if ($trimmed !== '' && ! str_starts_with($trimmed, '#')) {
                return $line;
            }
        }

        return '';
    }

    /** @param  array<int, string|null>  $values */
    private static function isComment(array $values): bool
    {
        return str_starts_with(ltrim((string) ($values[0] ?? '')), '#');
    }

    public static function normalizeHeader(string $name): string
    {
        return Str::of(Str::ascii($name))->lower()->trim()->replaceMatches('/[\s\-]+/', '_')->toString();
    }

    /**
     * @param  array<string, string>  $example
     */
    public static function template(array $example): string
    {
        $stream = self::memoryStream();
        fputcsv($stream, array_keys($example), ';', '"', '');
        fputcsv($stream, array_values($example), ';', '"', '');
        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return "\xEF\xBB\xBF".$csv;
    }

    /** @return resource */
    private static function memoryStream()
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new RuntimeException('No se pudo abrir el búfer temporal para el CSV.');
        }

        return $stream;
    }
}
