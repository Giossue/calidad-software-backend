<?php

namespace App\Imports\Importers;

use App\Actions\AcademicPeriods\CreateAcademicPeriod;
use App\Http\Requests\Api\V1\AcademicPeriods\StoreAcademicPeriodRequest;
use App\Imports\FormRequestValidator;
use App\Imports\Importer;
use App\Models\PeriodoAcademico;
use App\Models\Usuario;

class AcademicPeriodsImporter implements Importer
{
    public function __construct(
        private FormRequestValidator $validator,
        private CreateAcademicPeriod $createAcademicPeriod,
    ) {}

    public function columns(): array
    {
        return ['nombre' => true, 'fecha_inicio' => true, 'fecha_fin' => true];
    }

    public function example(): array
    {
        return ['nombre' => '2027-1S', 'fecha_inicio' => '2027-04-01', 'fecha_fin' => '2027-08-31'];
    }

    public function authorize(Usuario $user): bool
    {
        return $user->can('create', PeriodoAcademico::class);
    }

    public function import(array $row, Usuario $user): void
    {
        $data = $this->validator->validate(StoreAcademicPeriodRequest::class, [
            'nombre' => $row['nombre'],
            'fecha_inicio' => $row['fecha_inicio'],
            'fecha_fin' => $row['fecha_fin'],
        ], $user);

        $this->createAcademicPeriod->handle($data);
    }
}
