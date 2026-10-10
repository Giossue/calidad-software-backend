<?php

use App\Http\Requests\Api\V1\Tutoring\SubjectRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cada tutoría guarda una copia del nombre de su asignatura. Se alinea con el
 * nombre normalizado (mayúsculas, sin tildes) que ahora tienen las asignaturas.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('asignatura_tutoria')
            ->leftJoin('subjects', 'subjects.id', '=', 'asignatura_tutoria.subject_id')
            ->select(['asignatura_tutoria.id_asig_tutoria as id', 'asignatura_tutoria.nombre', 'subjects.name as subject_name'])
            ->orderBy('asignatura_tutoria.id_asig_tutoria')
            ->each(function (object $tutoring): void {
                $name = SubjectRequest::normalizeName($tutoring->subject_name ?? $tutoring->nombre);

                if ($name !== $tutoring->nombre) {
                    DB::table('asignatura_tutoria')->where('id_asig_tutoria', $tutoring->id)->update(['nombre' => $name]);
                }
            });
    }

    public function down(): void
    {
        // Los nombres originales no se pueden recuperar.
    }
};
