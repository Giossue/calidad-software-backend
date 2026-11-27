<?php

namespace App\Actions\Teacher;

use App\Models\AsignaturaTutoria;
use App\Models\InscripcionTutoria;
use App\Models\MetricaConocimiento;
use App\Models\Nota;
use App\Models\Usuario;
use App\Support\KnowledgeScale;
use App\Support\TeacherWorkspace;
use Illuminate\Validation\ValidationException;

class RegisterGrade
{
    public function __construct(private TeacherWorkspace $workspace, private ManageEnrollment $enrollments) {}

    public function execute(Usuario $teacher, AsignaturaTutoria $tutoring, InscripcionTutoria $enrollment, string $type, string $value): InscripcionTutoria
    {
        return $this->workspace->write($teacher, $tutoring, function () use ($teacher, $tutoring, $enrollment, $type, $value): InscripcionTutoria {
            $this->workspace->enrollment($tutoring, $enrollment);
            $locked = InscripcionTutoria::query()->whereKey($enrollment->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->estado || ! $locked->estudiante->estado || ! $locked->estudiante->hasRole('estudiante')) {
                throw ValidationException::withMessages(['value' => 'Solo puedes registrar notas de estudiantes con inscripción y cuenta activas.']);
            }
            if ($locked->isAbandoned()) {
                throw ValidationException::withMessages(['value' => 'El estudiante abandonó la tutoría; no se pueden registrar sus notas.']);
            }
            // Una sola nota por etapa: al corregirla se actualiza el valor, no se acumula un historial.
            $current = $locked->notas()->where('tipo', $type)->orderByDesc('id_nota')->first();
            if (! $current) {
                Nota::query()->create(['fk_inscripcion' => $locked->getKey(), 'tipo' => $type, 'valor' => $value, 'fecha_registro' => today()]);
            } elseif ((float) $current->valor !== (float) $value) {
                $current->update(['valor' => $value, 'fecha_registro' => today()]);
            }
            if ($type === 'diagnostic') {
                $group = KnowledgeScale::classify((float) $value);
                if (! $group) {
                    throw ValidationException::withMessages(['value' => 'La calificación no tiene un grupo de conocimiento configurado.']);
                }
                MetricaConocimiento::query()->updateOrCreate(['enrollment_id' => $locked->getKey()], [
                    'fk_id_usuario' => $locked->fk_id_usuario, 'descripcion' => $group['label'],
                    'rango' => $group['key'], 'nota_minima' => $group['min'], 'nota_maxima' => $group['max'], 'estado' => 'active',
                ]);
            }

            if ($type !== 'diagnostic') {
                $this->syncFinalResult($teacher, $tutoring, $locked);
            }

            return $locked->load(ManageEnrollment::RELATIONS);
        });
    }

    /**
     * Con los dos parciales, el resultado final (estado) se ajusta solo a lo que indican las notas.
     * Usa el mismo registro que la elección manual, así el aprobado avanza a la asignatura siguiente
     * y el estudiante recibe el aviso. Sin los dos parciales, o si la inscripción no está activa, no cambia nada.
     */
    public function syncFinalResult(Usuario $teacher, AsignaturaTutoria $tutoring, InscripcionTutoria $enrollment): void
    {
        if (! $enrollment->estado || $enrollment->isAbandoned()) {
            return;
        }
        $result = InscripcionTutoria::resultForPartials(
            $enrollment->notas()->where('tipo', 'partial')->orderByDesc('id_nota')->value('valor'),
            $enrollment->notas()->where('tipo', 'partial_two')->orderByDesc('id_nota')->value('valor'),
        );
        if ($result !== null && $result !== $enrollment->final_result) {
            $this->enrollments->recordFinalResult($teacher, $tutoring, $enrollment, $result);
        }
    }
}
