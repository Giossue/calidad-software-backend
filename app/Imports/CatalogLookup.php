<?php

namespace App\Imports;

use App\Models\Carrera;
use App\Models\Ciclo;
use App\Models\Facultad;
use App\Models\Modalidad;
use App\Models\Paralelo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Resuelve los nombres escritos en el CSV a identificadores, sin distinguir
 * mayúsculas, tildes ni espacios repetidos.
 */
class CatalogLookup
{
    public function careerId(?string $name, string $column = 'carrera'): int
    {
        return $this->find(Carrera::query()->where('estado', true)->get(['id_carrera', 'nombre']), $name, $column, 'La carrera');
    }

    public function facultyId(?string $name, string $column = 'facultad'): int
    {
        return $this->find(Facultad::query()->where('estado', true)->get(['id_facultad', 'nombre']), $name, $column, 'La facultad');
    }

    public function modalityId(?string $name, string $column = 'modalidad'): ?int
    {
        return $name === null ? null : $this->find(Modalidad::query()->where('estado', true)->get(['id_modalidad', 'nombre']), $name, $column, 'La modalidad');
    }

    public function sectionId(?string $name, string $column = 'paralelo'): ?int
    {
        return $name === null ? null : $this->find(Paralelo::query()->where('estado', true)->get(['id_paralelo', 'nombre']), $name, $column, 'El paralelo');
    }

    public function cycleId(int $careerId, ?string $number, string $column = 'ciclo'): ?int
    {
        if ($number === null) {
            return null;
        }

        $id = ctype_digit($number) ? Ciclo::query()
            ->where('fk_carrera', $careerId)
            ->where('numero', (int) $number)
            ->where('estado', true)
            ->orderByRaw('fk_paralelo IS NOT NULL')
            ->orderBy('id_ciclo')
            ->value('id_ciclo') : null;

        if ($id === null) {
            throw ValidationException::withMessages([$column => "El ciclo «{$number}» no existe en la carrera indicada."]);
        }

        return (int) $id;
    }

    public static function normalize(string $value): string
    {
        return Str::of(Str::ascii($value))->lower()->squish()->toString();
    }

    /** @param iterable<Model> $records */
    private function find(iterable $records, ?string $name, string $column, string $label): int
    {
        if ($name === null) {
            throw ValidationException::withMessages([$column => "El campo {$column} es obligatorio."]);
        }

        $needle = self::normalize($name);
        foreach ($records as $record) {
            if (self::normalize((string) $record->getAttribute('nombre')) === $needle) {
                return (int) $record->getKey();
            }
        }

        throw ValidationException::withMessages([$column => "{$label} «{$name}» no existe o está inactiva."]);
    }
}
