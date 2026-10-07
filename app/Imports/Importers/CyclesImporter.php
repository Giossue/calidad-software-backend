<?php

namespace App\Imports\Importers;

use App\Actions\Academic\CreateCycle;
use App\Http\Requests\Api\V1\Admin\StoreCycleRequest;
use App\Imports\CatalogLookup;
use App\Imports\FormRequestValidator;
use App\Imports\Importer;
use App\Models\Ciclo;
use App\Models\Usuario;

class CyclesImporter implements Importer
{
    public function __construct(
        private FormRequestValidator $validator,
        private CatalogLookup $lookup,
        private CreateCycle $createCycle,
    ) {}

    public function columns(): array
    {
        return ['carrera' => true, 'numero' => true, 'nombre' => true];
    }

    public function example(): array
    {
        return ['carrera' => 'Software', 'numero' => '9', 'nombre' => 'Noveno ciclo'];
    }

    public function authorize(Usuario $user): bool
    {
        return $user->can('create', Ciclo::class);
    }

    public function import(array $row, Usuario $user): void
    {
        $data = $this->validator->validate(StoreCycleRequest::class, [
            'career_id' => $this->lookup->careerId($row['carrera']),
            'number' => $row['numero'],
            'name' => $row['nombre'],
        ], $user);

        $this->createCycle->handle($data);
    }
}
