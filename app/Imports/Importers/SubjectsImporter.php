<?php

namespace App\Imports\Importers;

use App\Actions\Tutoring\ManageSubject;
use App\Http\Requests\Api\V1\Tutoring\SubjectRequest;
use App\Imports\CatalogLookup;
use App\Imports\FormRequestValidator;
use App\Imports\Importer;
use App\Models\Subject;
use App\Models\Usuario;

class SubjectsImporter implements Importer
{
    public function __construct(
        private FormRequestValidator $validator,
        private CatalogLookup $lookup,
        private ManageSubject $subjects,
    ) {}

    public function columns(): array
    {
        return ['carrera' => true, 'nombre' => true, 'codigo' => false, 'ciclo' => false, 'modalidad' => false];
    }

    public function example(): array
    {
        return ['carrera' => 'Software', 'nombre' => 'Programación I', 'codigo' => 'SW-101', 'ciclo' => '1', 'modalidad' => 'Presencial'];
    }

    public function authorize(Usuario $user): bool
    {
        return $user->can('create', Subject::class);
    }

    public function import(array $row, Usuario $user): void
    {
        $careerId = $this->lookup->careerId($row['carrera']);
        $data = $this->validator->validate(SubjectRequest::class, array_filter([
            'career_id' => $careerId,
            'name' => $row['nombre'],
            'code' => $row['codigo'],
            'cycle_id' => $this->lookup->cycleId($careerId, $row['ciclo']),
            'modality_id' => $this->lookup->modalityId($row['modalidad']),
        ], fn (mixed $value): bool => $value !== null), $user);

        $this->subjects->create($user, [
            'career_id' => $careerId,
            'code' => $data['code'] ?? null,
            'name' => $data['name'],
            'cycle_id' => $data['cycle_id'] ?? null,
            'modality_id' => $data['modality_id'] ?? null,
        ]);
    }
}
