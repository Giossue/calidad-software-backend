<?php

namespace Tests\Feature\Api\V1\Tutoring;

use App\Models\AsignaturaTutoria;
use App\Models\Carrera;
use App\Models\Ciclo;
use App\Models\Facultad;
use App\Models\Modalidad;
use App\Models\Paralelo;
use App\Models\PeriodoAcademico;
use App\Models\Subject;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class TutoringTestCase extends TestCase
{
    use RefreshDatabase;

    protected const API = '/api/v1/tutoring-coordination';

    protected Usuario $coordinator;

    protected Carrera $career;

    protected Carrera $otherCareer;

    protected Ciclo $cycle;

    protected Ciclo $otherCycle;

    protected Modalidad $modality;

    protected PeriodoAcademico $period;

    protected function setUp(): void
    {
        parent::setUp();

        // Fecha dentro del PAO de los fixtures: un período vencido se desactiva solo.
        $this->travelTo('2026-09-15 12:00:00');

        $faculty = Facultad::query()->create(['nombre' => 'Ciencias', 'estado' => true]);
        $this->modality = Modalidad::query()->create(['nombre' => 'Presencial', 'estado' => true]);
        $this->period = PeriodoAcademico::query()->create([
            'nombre' => 'PAO 2026', 'fecha_inicio' => '2026-05-01',
            'fecha_fin' => '2026-10-31', 'estado' => true,
        ]);
        $this->career = Carrera::query()->create([
            'fk_facultad' => $faculty->getKey(), 'fk_modalidad' => $this->modality->getKey(),
            'nombre' => 'Software', 'estado' => true,
        ]);
        $this->otherCareer = Carrera::query()->create([
            'fk_facultad' => $faculty->getKey(), 'fk_modalidad' => $this->modality->getKey(),
            'nombre' => 'Administración', 'estado' => true,
        ]);
        $section = Paralelo::query()->create(['nombre' => 'A', 'estado' => true]);
        $this->cycle = $this->createCycle($this->career, $section);
        $this->otherCycle = $this->createCycle($this->otherCareer, $section);
        $this->coordinator = Usuario::factory()->withRole('coordinador_carrera')->create();
        $this->coordinator->coordinatedCareers()->attach($this->career);
    }

    protected function createCycle(Carrera $career, ?Paralelo $section = null, int $number = 1): Ciclo
    {
        $section ??= Paralelo::query()->create(['nombre' => 'B', 'estado' => true]);

        return Ciclo::query()->create([
            'fk_carrera' => $career->getKey(), 'fk_paralelo' => $section->getKey(),
            'nombre' => "Ciclo {$number}", 'numero' => $number, 'estado' => true,
        ]);
    }

    protected function createSubject(?Ciclo $cycle = null, string $code = 'CS-101'): Subject
    {
        $cycle ??= $this->cycle;
        $subject = Subject::query()->create([
            'career_id' => $cycle->fk_carrera, 'code' => $code,
            'name' => 'Calidad de software', 'is_active' => true,
        ]);
        $subject->cycles()->attach($cycle);

        return $subject;
    }

    protected function createTutoring(?Ciclo $cycle = null, ?Subject $subject = null): AsignaturaTutoria
    {
        $cycle ??= $this->cycle;
        $subject ??= $this->createSubject($cycle);

        return AsignaturaTutoria::query()->create([
            'subject_id' => $subject->getKey(), 'fk_ciclo' => $cycle->getKey(),
            'fk_periodo' => $this->period->getKey(), 'fk_modalidad' => $this->modality->getKey(),
            'fk_paralelo' => $cycle->fk_paralelo, 'fk_docente' => null,
            'nombre' => $subject->name, 'estado' => true,
        ]);
    }

    protected function tutoringPayload(Subject $subject, ?Ciclo $cycle = null): array
    {
        return [
            'subject_id' => $subject->getKey(), 'cycle_id' => ($cycle ?? $this->cycle)->getKey(),
            'period_id' => $this->period->getKey(), 'modality_id' => $this->modality->getKey(),
            'schedules' => [['day' => 'lunes', 'start_time' => '10:00', 'end_time' => '11:00', 'room' => 'Aula 101']],
        ];
    }

    protected function teacherPayload(?Carrera $career = null): array
    {
        $user = Usuario::factory()->make();

        return [
            'career_id' => ($career ?? $this->career)->getKey(),
            'identification' => $user->cedula, 'name' => 'María Pérez',
            'email' => 'maria.perez@ueb.edu.ec', 'phone' => '0991234567',
        ];
    }
}
