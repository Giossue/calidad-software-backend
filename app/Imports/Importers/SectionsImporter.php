<?php

namespace App\Imports\Importers;

use App\Actions\Sections\RegisterPeriodSection;
use App\Http\Requests\Api\V1\Coordination\RegisterPeriodSectionRequest;
use App\Imports\FormRequestValidator;
use App\Imports\Importer;
use App\Models\PeriodoAcademico;
use App\Models\TemaTitulacion;
use App\Models\Usuario;
use Illuminate\Validation\ValidationException;

class SectionsImporter implements Importer
{
    public function __construct(
        private FormRequestValidator $validator,
        private RegisterPeriodSection $registerSection,
    ) {}

    public function columns(): array
    {
        return ['nombre' => true];
    }

    public function example(): array
    {
        return ['nombre' => 'A'];
    }

    public function authorize(Usuario $user): bool
    {
        return $user->can('viewAny', TemaTitulacion::class);
    }

    public function import(array $row, Usuario $user): void
    {
        $data = $this->validator->validate(RegisterPeriodSectionRequest::class, ['name' => $row['nombre']], $user);
        $period = PeriodoAcademico::periodoVigente()
            ?? throw ValidationException::withMessages(['nombre' => 'No existe un período académico vigente.']);

        $this->registerSection->handle($period, $data['name']);
    }
}
