<?php

namespace App\Imports\Importers;

use App\Actions\Academic\CreateCareer;
use App\Imports\CatalogLookup;
use App\Imports\FormRequestValidator;
use App\Imports\Importer;
use App\Models\Carrera;
use App\Models\Usuario;

class CareersImporter implements Importer
{
    public function __construct(
        private FormRequestValidator $validator,
        private CatalogLookup $lookup,
        private CreateCareer $createCareer,
    ) {}

    public function columns(): array
    {
        return ['facultad' => true, 'nombre' => true, 'ciclos' => false, 'modalidad' => false, 'codigo' => false];
    }

    public function example(): array
    {
        return ['facultad' => 'Facultad de Ciencias Administrativas', 'nombre' => 'Software', 'ciclos' => '8', 'modalidad' => 'Presencial', 'codigo' => 'SW-2024'];
    }

    public function authorize(Usuario $user): bool
    {
        return $user->can('create', Carrera::class);
    }

    public function import(array $row, Usuario $user): ?string
    {
        $data = $this->validator->validate(CareerRowRequest::class, array_filter([
            'faculty_id' => $this->lookup->facultyId($row['facultad']),
            'name' => $row['nombre'],
            'code' => $row['codigo'],
            'cycles_count' => $row['ciclos'],
            'modality_id' => $this->lookup->modalityId($row['modalidad']),
        ], fn (mixed $value): bool => $value !== null), $user);

        $this->createCareer->handle($data);

        return null;
    }
}
