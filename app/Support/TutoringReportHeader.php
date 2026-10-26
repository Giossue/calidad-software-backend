<?php

namespace App\Support;

use App\Models\AsignaturaTutoria;

/** Datos informativos comunes a los formatos oficiales de Tutorías Académicas. */
class TutoringReportHeader
{
    /**
     * @return array{faculty: string|null, career: string|null, subject: string, cycle: int|null, cycle_name: string|null, academic_period: string|null, section: string|null, coordinator: string|null}
     */
    public static function for(AsignaturaTutoria $tutoring): array
    {
        $tutoring->loadMissing(['ciclo.carrera.facultad', 'ciclo.carrera.coordinators', 'periodo', 'paralelo']);
        $career = $tutoring->ciclo?->carrera;

        return [
            'faculty' => $career?->facultad?->nombre,
            'career' => $career?->nombre,
            'subject' => $tutoring->nombre,
            'cycle' => $tutoring->ciclo?->numero,
            'cycle_name' => $tutoring->ciclo?->nombre,
            'academic_period' => $tutoring->periodo?->nombre,
            'section' => $tutoring->paralelo?->nombre,
            'coordinator' => $career?->coordinators->firstWhere('estado', true)?->nombre,
        ];
    }
}
