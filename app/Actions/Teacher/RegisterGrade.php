<?php

namespace App\Actions\Teacher;

use App\Models\AsignaturaTutoria;
use App\Models\InscripcionTutoria;
use App\Models\MetricaConocimiento;
use App\Models\Nota;
use App\Models\Usuario;
use App\Support\TeacherWorkspace;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;

class RegisterGrade
{
    public function __construct(private TeacherWorkspace $workspace) {}

    public function execute(Usuario $teacher, AsignaturaTutoria $tutoring, InscripcionTutoria $enrollment, string $type, string $value): InscripcionTutoria
    {
        return $this->workspace->write($teacher, $tutoring, function () use ($tutoring, $enrollment, $type, $value): InscripcionTutoria {
            $this->workspace->enrollment($tutoring, $enrollment);
            $locked = InscripcionTutoria::query()->whereKey($enrollment->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->estado || ! $locked->estudiante->estado || ! $locked->estudiante->hasRole('estudiante')) {
                throw ValidationException::withMessages(['value' => 'Solo puedes registrar notas de estudiantes con inscripción y cuenta activas.']);
            }
            $previous = $locked->notas()->where('tipo', $type)->orderByDesc('id_nota')->first();
            // Keep grade history. An identical retry does not insert another grade.
            if (! $previous || (float) $previous->valor !== (float) $value) {
                Nota::query()->create(['fk_inscripcion' => $locked->getKey(), 'tipo' => $type, 'valor' => $value, 'fecha_registro' => today()]);
            }
            if ($type === 'diagnostic') {
                $group = collect(Config::array('teaching.groups'))->first(fn (array $item) => (float) $value >= $item['min'] && (float) $value <= $item['max']);
                if (! $group) {
                    throw ValidationException::withMessages(['value' => 'La calificación no tiene un grupo de conocimiento configurado.']);
                }
                MetricaConocimiento::query()->updateOrCreate(['enrollment_id' => $locked->getKey()], [
                    'fk_id_usuario' => $locked->fk_id_usuario, 'descripcion' => $group['label'],
                    'rango' => $group['key'], 'nota_minima' => $group['min'], 'nota_maxima' => $group['max'], 'estado' => 'active',
                ]);
            }

            return $locked->load(ManageEnrollment::RELATIONS);
        });
    }
}
