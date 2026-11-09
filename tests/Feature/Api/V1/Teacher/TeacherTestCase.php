<?php

namespace Tests\Feature\Api\V1\Teacher;

use App\Models\AsignaturaTutoria;
use App\Models\Horario;
use App\Models\InscripcionTutoria;
use App\Models\Usuario;
use App\Support\ClassHours;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\V1\Tutoring\TutoringTestCase;

abstract class TeacherTestCase extends TutoringTestCase
{
    protected Usuario $teacher;

    protected AsignaturaTutoria $tutoring;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-29 12:00:00'));
        Notification::fake();
        $this->teacher = Usuario::factory()->withRole('docente')->create();
        $this->tutoring = $this->createTutoring();
        $this->tutoring->update(['fk_docente' => $this->teacher->getKey()]);
        Sanctum::actingAs($this->teacher, ['access-api']);
    }

    protected function path(string $suffix = '', ?AsignaturaTutoria $tutoring = null): string
    {
        return '/api/v1/teacher/tutorings/'.($tutoring ?? $this->tutoring)->getKey().$suffix;
    }

    protected function enrollment(?AsignaturaTutoria $tutoring = null, ?Usuario $student = null): InscripcionTutoria
    {
        $tutoring ??= $this->tutoring;
        $student ??= Usuario::factory()->student()->create();
        $student->paralelos()->syncWithoutDetaching([$tutoring->fk_paralelo => ['fecha_asignacion' => today(), 'estado' => true]]);

        return InscripcionTutoria::query()->create(['fk_asig_tutoria' => $tutoring->getKey(), 'fk_id_usuario' => $student->getKey(), 'fecha_inscripcion' => today(), 'estado' => true]);
    }

    /** Horario activo que está en curso ahora (hora local): habilita la toma de asistencia. */
    protected function classNow(?AsignaturaTutoria $tutoring = null): Horario
    {
        $now = ClassHours::now();
        $days = [0 => 'domingo', 1 => 'lunes', 2 => 'martes', 3 => 'miercoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sabado'];

        return Horario::query()->create([
            'fk_asig_tutoria' => ($tutoring ?? $this->tutoring)->getKey(), 'dia_semana' => $days[$now->dayOfWeek],
            'hora_inicio' => $now->subMinutes(30)->format('H:i:00'), 'hora_fin' => $now->addMinutes(90)->format('H:i:00'),
            'room' => 'Aula 1', 'estado' => true,
        ]);
    }
}
