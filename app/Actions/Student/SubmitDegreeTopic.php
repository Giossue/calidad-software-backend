<?php

namespace App\Actions\Student;

use App\Models\TemaTitulacion;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

class SubmitDegreeTopic
{
    /**
     * Registra una nueva propuesta de tema de titulación para el estudiante,
     * asociándola al período académico vigente e iniciando el flujo de revisión.
     */
    public function handle(
        Usuario $student,
        string $title,
        ?string $description,
        int $periodId,
        ?int $sectionId = null,
    ): TemaTitulacion {
        return DB::transaction(function () use ($student, $title, $description, $periodId, $sectionId): TemaTitulacion {
            if ($sectionId) {
                $student->paralelos()->syncWithoutDetaching([
                    $sectionId => [
                        'fecha_asignacion' => now()->toDateString(),
                        'estado' => true,
                    ],
                ]);
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
}
