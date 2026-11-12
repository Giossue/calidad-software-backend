<?php

namespace App\Imports;

use Illuminate\Validation\ValidationException;
use Smalot\PdfParser\Parser;
use Throwable;
use ZipArchive;

/**
 * Lee el archivo de la malla curricular de una carrera y devuelve sus asignaturas:
 * `[code, name, cycle, previous]`, donde `previous` son los códigos (o nombres)
 * de las asignaturas que hay que aprobar antes.
 *
 * - CSV y Excel (.xlsx): columnas codigo, nombre, ciclo y previas (separadas por `|`).
 * - PDF: se toman los códigos de asignatura (p. ej. SW-B1-001) con el nombre que
 *   los acompaña en la misma línea, si lo hay; el ciclo se deduce del código.
 *   Las secuencias de un PDF con flechas no se pueden leer: se completan en la
 *   vista previa.
 */
class CurriculumFile
{
    public const MAX_ROWS = 200;

    private const HEADERS = [
        'code' => ['codigo', 'cod', 'code'],
        'name' => ['nombre', 'asignatura', 'materia', 'name'],
        'cycle' => ['ciclo', 'nivel', 'semestre', 'cycle'],
        'previous' => ['previas', 'previa', 'prerrequisitos', 'prerrequisito', 'prerequisitos', 'requisitos', 'requisito'],
    ];

    /** Código de asignatura con el número de ciclo en el segundo tramo: SW-B1-001, TI-P4-016, SW-IC8-038. */
    private const CODE = '/\b([A-Z]{2,5})-([A-Z]{0,3})(\d{1,2})-(\d{2,4})\b/u';

    /** @return list<array{code: string|null, name: string, cycle: int|null, previous: list<string>}> */
    public function read(string $path, string $extension): array
    {
        $rows = match (strtolower($extension)) {
            'csv', 'txt' => $this->fromTable($this->csv((string) file_get_contents($path))),
            'xlsx' => $this->fromTable($this->xlsx($path)),
            'pdf' => $this->fromPdf($path),
            default => throw ValidationException::withMessages(['file' => 'Sube la malla en Excel (.xlsx), CSV o PDF.']),
        };

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'No se encontraron asignaturas en el archivo. Usa la plantilla: columnas codigo, nombre, ciclo y previas.']);
        }
        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => 'La malla no puede tener más de '.self::MAX_ROWS.' asignaturas.']);
        }

        return $rows;
    }

    /** Número de ciclo desde «3», «3.º», «III» o «Tercero». */
    public static function cycleNumber(?string $value): ?int
    {
        $value = mb_strtoupper(trim((string) $value));
        if ($value === '') {
            return null;
        }
        if (preg_match('/^(\d{1,2})/', $value, $match)) {
            return (int) $match[1];
        }
        $roman = ['I' => 1, 'II' => 2, 'III' => 3, 'IV' => 4, 'V' => 5, 'VI' => 6, 'VII' => 7, 'VIII' => 8, 'IX' => 9, 'X' => 10, 'XI' => 11, 'XII' => 12];
        if (isset($roman[$value])) {
            return $roman[$value];
        }
        $words = ['PRIMER' => 1, 'SEGUND' => 2, 'TERCER' => 3, 'CUART' => 4, 'QUINT' => 5, 'SEXT' => 6, 'SEPTIM' => 7, 'OCTAV' => 8, 'NOVEN' => 9, 'DECIM' => 10];
        $ascii = CatalogLookup::normalize($value);
        foreach ($words as $prefix => $number) {
            if (str_starts_with($ascii, mb_strtolower($prefix))) {
                return $number;
            }
        }

        return null;
    }

    /** Ciclo indicado en el código (SW-B3-014 → 3), si lo tiene. */
    public static function cycleFromCode(string $code): ?int
    {
        return preg_match(self::CODE, mb_strtoupper($code), $match) ? (int) $match[3] : null;
    }

    /**
     * @param  list<list<string>>  $table
     * @return list<array{code: string|null, name: string, cycle: int|null, previous: list<string>}>
     */
    private function fromTable(array $table): array
    {
        $header = null;
        $rows = [];
        foreach ($table as $cells) {
            $cells = array_map(fn ($cell): string => trim((string) $cell), $cells);
            if (implode('', $cells) === '' || str_starts_with($cells[0] ?? '', '#')) {
                continue;
            }
            if ($header === null) {
                $header = $this->header($cells);

                continue;
            }
            $value = fn (string $field): string => isset($header[$field]) ? ($cells[$header[$field]] ?? '') : '';
            $name = $value('name');
            $code = $value('code');
            if ($name === '' && $code === '') {
                continue;
            }
            $rows[] = [
                'code' => $code !== '' ? $code : null,
                'name' => $name,
                'cycle' => self::cycleNumber($value('cycle')) ?? ($code !== '' ? self::cycleFromCode($code) : null),
                'previous' => self::splitList($value('previous')),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $cells
     * @return array<string, int>
     */
    private function header(array $cells): array
    {
        $found = [];
        foreach ($cells as $index => $cell) {
            $name = CsvReader::normalizeHeader($cell);
            foreach (self::HEADERS as $field => $aliases) {
                if (! isset($found[$field]) && in_array($name, $aliases, true)) {
                    $found[$field] = $index;
                }
            }
        }
        if (! isset($found['name'])) {
            throw ValidationException::withMessages(['file' => 'Falta la columna «nombre». Usa la plantilla: columnas codigo, nombre, ciclo y previas.']);
        }

        return $found;
    }

    /** @return list<string> */
    public static function splitList(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[|;\n]+/u', $value) ?: []), fn (string $item): bool => $item !== ''));
    }

    /** @return list<list<string>> */
    private function csv(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? '';
        if (! mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        }
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];
        $first = collect($lines)->first(fn (string $line): bool => trim($line) !== '' && ! str_starts_with(ltrim($line, '"'), '#')) ?? '';
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';

        return array_values(array_map(fn (string $line): array => array_values(array_map(fn ($cell): string => (string) $cell, str_getcsv($line, $delimiter, '"', ''))), $lines));
    }

    /**
     * Primera hoja de un .xlsx (texto de cada celda).
     *
     * @return list<list<string>>
     */
    private function xlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'No se pudo abrir el archivo de Excel.']);
        }
        try {
            $strings = [];
            $shared = $zip->getFromName('xl/sharedStrings.xml');
            if ($shared !== false) {
                $xml = simplexml_load_string($shared);
                foreach ($xml !== false ? $xml->si : [] as $item) {
                    $strings[] = isset($item->t) ? (string) $item->t : implode('', array_map(fn ($run): string => (string) $run->t, iterator_to_array($item->r, false)));
                }
            }
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheet === false) {
                for ($index = 0; $index < $zip->numFiles; $index++) {
                    $name = (string) $zip->getNameIndex($index);
                    if (str_starts_with($name, 'xl/worksheets/') && str_ends_with($name, '.xml')) {
                        $sheet = $zip->getFromName($name);
                        break;
                    }
                }
            }
            $xml = $sheet !== false ? simplexml_load_string($sheet) : false;
            if ($xml === false) {
                throw ValidationException::withMessages(['file' => 'El archivo de Excel no tiene hojas legibles.']);
            }

            $table = [];
            foreach ($xml->sheetData->row as $row) {
                $cells = [];
                foreach ($row->c as $cell) {
                    $column = self::columnIndex(preg_replace('/\d+/', '', (string) $cell['r']) ?? 'A');
                    $type = (string) $cell['t'];
                    $cells[$column] = match ($type) {
                        's' => $strings[(int) $cell->v] ?? '',
                        'inlineStr' => (string) ($cell->is->t ?? ''),
                        default => (string) $cell->v,
                    };
                }
                $table[] = $cells === [] ? [] : array_values(array_replace(array_fill(0, max(array_keys($cells)) + 1, ''), $cells));
            }

            return $table;
        } finally {
            $zip->close();
        }
    }

    private static function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split(strtoupper($letters)) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return max($index - 1, 0);
    }

    /** @return list<array{code: string|null, name: string, cycle: int|null, previous: list<string>}> */
    private function fromPdf(string $path): array
    {
        try {
            $text = (new Parser)->parseFile($path)->getText();
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => 'No se pudo leer el PDF. Sube la malla en Excel o CSV con la plantilla.']);
        }

        return self::parsePdfText($text);
    }

    /** @return list<array{code: string|null, name: string, cycle: int|null, previous: list<string>}> */
    public static function parsePdfText(string $text): array
    {
        $rows = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', $line) ?? '');
            if (! preg_match_all(self::CODE, mb_strtoupper($line), $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            $codes = array_map(fn (array $match): string => $match[0], $matches[0]);
            // Línea de tabla: «CÓDIGO NOMBRE [ciclo] [previas…]».
            $name = '';
            if (str_starts_with(mb_strtoupper($line), $codes[0])) {
                // El nombre termina donde empiezan las previas o los números (ciclo, horas, créditos).
                $rest = trim(substr($line, strlen($codes[0])));
                $beforePrevious = mb_substr($rest, 0, mb_strlen((string) (preg_split(self::CODE, mb_strtoupper($rest))[0] ?? '')));
                $name = trim((string) preg_replace('/(\s+(\d+|[IVX]+)(\.º|°)?)+$/u', '', trim($beforePrevious)));
            }
            if ($name !== '' && preg_match('/\p{L}{3,}/u', $name)) {
                $rows[$codes[0]] = ['code' => $codes[0], 'name' => $name, 'cycle' => self::cycleFromCode($codes[0]), 'previous' => array_slice($codes, 1)];

                continue;
            }
            foreach ($codes as $code) {
                $rows[$code] ??= ['code' => $code, 'name' => '', 'cycle' => self::cycleFromCode($code), 'previous' => []];
            }
        }

        return array_values($rows);
    }
}
