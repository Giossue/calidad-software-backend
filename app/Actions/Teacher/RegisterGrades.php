<?php

namespace App\Actions\Teacher;

use App\Http\Requests\Api\V1\Teacher\BulkGradesRequest;
use App\Models\AsignaturaTutoria;
use App\Models\InscripcionTutoria;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/** Registra en una sola operación las notas de varios estudiantes: o se guardan todas, o ninguna. */
class RegisterGrades
{
    public function __construct(private RegisterGrade $registerGrade) {}

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return Collection<int, InscripcionTutoria>
     */
    public function execute(Usuario $teacher, AsignaturaTutoria $tutoring, array $entries): Collection
    {
        return DB::transaction(function () use ($teacher, $tutoring, $entries): Collection {
            $enrollments = InscripcionTutoria::query()->whereIn('id_inscripcion', array_column($entries, 'enrollment_id'))->get()->keyBy('id_inscripcion');
            $updated = new Collection;

            foreach ($entries as $entry) {
                $enrollment = $enrollments->get((int) $entry['enrollment_id']);
                foreach (BulkGradesRequest::TYPES as $type) {
                    if (isset($entry[$type]) && $entry[$type] !== '') {
                        $enrollment = $this->registerGrade->execute($teacher, $tutoring, $enrollment, $type, (string) $entry[$type]);
                    }
                }
                $updated->push($enrollment->loadMissing(ManageEnrollment::RELATIONS));
            }

            return $updated;
        });
    }
}
