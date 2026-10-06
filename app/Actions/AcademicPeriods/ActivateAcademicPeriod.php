<?php

namespace App\Actions\AcademicPeriods;

use App\Models\PeriodoAcademico;
use Symfony\Component\HttpFoundation\Response;

class ActivateAcademicPeriod
{
    public function handle(PeriodoAcademico $academicPeriod): PeriodoAcademico
    {
        $today = now()->toDateString();
        $inicio = $academicPeriod->fecha_inicio->toDateString();
        $fin = $academicPeriod->fecha_fin->toDateString();

        if ($fin < $today) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'No se puede habilitar un período académico cuya fecha de finalización ya ha expirado.');
        }

        if ($inicio > $today) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, "El período académico no puede habilitarse antes de su fecha de inicio ({$inicio}).");
        }

        // Solo un período puede estar activo al mismo tiempo: desactivar todos los demás
        PeriodoAcademico::query()
            ->where('id_periodo', '!=', $academicPeriod->getKey())
            ->where('estado', true)
            ->update(['estado' => false]);

        $academicPeriod->update(['estado' => true]);

        return $academicPeriod->refresh();
    }
}
