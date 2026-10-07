<?php

namespace App\Imports\Importers;

use App\Http\Requests\Api\V1\Admin\StoreFacultyRequest;
use App\Imports\FormRequestValidator;
use App\Imports\Importer;
use App\Models\Facultad;
use App\Models\Usuario;

class FacultiesImporter implements Importer
{
    public function __construct(private FormRequestValidator $validator) {}

    public function columns(): array
    {
        return ['nombre' => true];
    }

    public function example(): array
    {
        return ['nombre' => 'Facultad de Ciencias Administrativas'];
    }

    public function authorize(Usuario $user): bool
    {
        return $user->can('create', Facultad::class);
    }

    public function import(array $row, Usuario $user): void
    {
        $data = $this->validator->validate(StoreFacultyRequest::class, ['name' => $row['nombre']], $user);

        Facultad::query()->create(['nombre' => $data['name']]);
    }
}
