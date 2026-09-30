<?php

namespace App\Actions\Student;

use App\Models\Paralelo;
use App\Models\PeriodoAcademico;
use App\Models\TemaTitulacion;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

class SubmitDegreeTopic
{
    /**
     * Registra una nueva propuesta de tema de titulación para el estudiante,
     * garantizando que quede automáticamente asignado a un paralelo e iniciando
     * el proceso de aprobación con la Coordinación de Titulación.
     */
    public function handle(
        Usuario $student,
        string $title,
        ?string $description,
        int $periodId,
        ?int $sectionId = null,
        bool $replacePending = false,
    ): TemaTitulacion {
        return DB::transaction(function () use ($student, $title, $description, $periodId, $sectionId, $replacePending): TemaTitulacion {
            $this->ensureStudentAssignedToSection($student, $periodId, $sectionId);

            if ($replacePending) {
                TemaTitulacion::query()
                    ->where('fk_id_usuario', $student->getKey())
                    ->where('fk_periodo', $periodId)
                    ->where('estado', 'pendiente')
                    ->update(['estado' => 'descartado']);
            }

            /** @var TemaTitulacion $topic */
            $topic = TemaTitulacion::query()->create([
                'fk_id_usuario' => $student->getKey(),
                'fk_periodo' => $periodId,
                'titulo' => $title,
                'descripcion' => $description,
                'estado' => 'pendiente',
                'fecha_propuesta' => now()->toDateString(),
                'fecha_revision' => null,
                'fk_coord_revisor' => null,
            ]);

            return $topic;
        });
    }

    /**
     * Modifica y actualiza una propuesta de titulación pendiente de revisión ("cambiarla").
     */
    public function update(
        TemaTitulacion $topic,
        string $title,
        ?string $description,
        ?int $sectionId = null,
    ): TemaTitulacion {
        return DB::transaction(function () use ($topic, $title, $description, $sectionId): TemaTitulacion {
            /** @var Usuario $student */
            $student = $topic->estudiante;
            $this->ensureStudentAssignedToSection($student, $topic->fk_periodo, $sectionId);

            $topic->update([
                'titulo' => $title,
                'descripcion' => $description,
                'fecha_propuesta' => now()->toDateString(),
            ]);

            return $topic;
        });
    }

    /**
     * Garantiza que el estudiante quede automáticamente asignado a un paralelo
     * si aún no tiene uno registrado en el sistema.
     */
    private function ensureStudentAssignedToSection(Usuario $student, int $periodId, ?int $explicitSectionId): void
    {
        $resolvedSectionId = $explicitSectionId;

        if (! $resolvedSectionId && $student->paralelos()->count() === 0) {
            $period = PeriodoAcademico::query()->find($periodId);

            $resolvedSectionId = $period?->paralelos()->wherePivot('estado', true)->value('paralelo.id_paralelo')
                ?? $period?->paralelos()->value('paralelo.id_paralelo')
                ?? Paralelo::query()->where('estado', true)->value('id_paralelo')
                ?? Paralelo::query()->firstOrCreate(['nombre' => 'A'], ['estado' => true])->getKey();
        }

        if ($resolvedSectionId) {
            $student->paralelos()->syncWithoutDetaching([
                $resolvedSectionId => [
                    'fecha_asignacion' => now()->toDateString(),
                    'estado' => true,
                ],
            ]);
        }
    }
}
