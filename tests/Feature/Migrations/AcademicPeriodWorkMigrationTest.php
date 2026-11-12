<?php

namespace Tests\Feature\Migrations;

use App\Models\AsignaturaTutoria;
use App\Models\Ciclo;
use App\Models\PeriodoAcademico;
use App\Models\Subject;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Api\V1\Tutoring\TutoringTestCase;

class AcademicPeriodWorkMigrationTest extends TutoringTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));
    }

    public function test_existing_active_periods_are_reduced_to_the_current_one_before_the_unique_index(): void
    {
        $migration = $this->migration('2026_11_12_100000_enforce_single_active_academic_period.php');
        $migration->down();
        $future = PeriodoAcademico::query()->create(['nombre' => 'PAO 2027', 'fecha_inicio' => '2027-01-01', 'fecha_fin' => '2027-06-30', 'estado' => true]);

        $migration->up();

        $this->assertTrue($this->period->refresh()->estado);
        $this->assertFalse($future->refresh()->estado);
        $migration->down();
        $future->update(['estado' => true]);
        $this->assertSame(2, PeriodoAcademico::query()->where('estado', true)->count());
        $future->update(['estado' => false]);
        $migration->up();
    }

    public function test_student_period_records_are_backfilled_with_the_paralelo_row_of_the_highest_active_cycle(): void
    {
        $migration = $this->migration('2026_11_12_110000_create_estudiante_periodo_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('estudiante_periodo'));
        $baseTwo = Ciclo::query()->create(['fk_carrera' => $this->career->getKey(), 'fk_paralelo' => null, 'nombre' => 'Segundo ciclo', 'numero' => 2, 'estado' => true]);
        $cycleTwo = $this->createCycle($this->career, $this->cycle->paralelo, 2);
        $cycleThree = $this->createCycle($this->career, $this->cycle->paralelo, 3);
        $student = Usuario::factory()->withRole('estudiante')->create(['fk_carrera' => $this->career->getKey(), 'fk_ciclo' => $this->cycle->getKey()]);
        $withoutBase = Usuario::factory()->withRole('estudiante')->create(['fk_carrera' => $this->career->getKey()]);
        $this->enroll($student, $this->createTutoring(), true);
        $this->enroll($student, $this->createTutoring($cycleTwo, $this->createSubject($cycleTwo, 'CS-201', 'Redes')), true);
        $this->enroll($student, $this->createTutoring($cycleThree, $this->createSubject($cycleThree, 'CS-301', 'Bases')), false);
        $this->enroll($withoutBase, $this->createTutoring($cycleThree, $this->createSubject($cycleThree, 'CS-302', 'Sistemas')), true);

        $migration->up();

        $this->assertSame($cycleTwo->getKey(), $this->recordCycle($student));
        $this->assertNotSame($baseTwo->getKey(), $this->recordCycle($student));
        $this->assertSame($cycleThree->getKey(), $this->recordCycle($withoutBase));
        $this->assertSame($this->cycle->getKey(), $student->refresh()->fk_ciclo);
        $this->assertSame(2, DB::table('estudiante_periodo')->count());
    }

    public function test_subjects_take_the_lowest_number_of_their_attached_cycles(): void
    {
        $migration = $this->migration('2026_11_12_120000_add_cycle_number_to_subjects_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('subjects', 'cycle_number'));
        $cycleTwo = $this->createCycle($this->career, $this->cycle->paralelo, 2);
        $cycleThree = $this->createCycle($this->career, $this->cycle->paralelo, 3);
        $subject = $this->createSubject($cycleThree);
        $subject->cycles()->attach($cycleTwo);
        $loose = Subject::query()->create(['career_id' => $this->career->getKey(), 'name' => 'Libre', 'is_active' => true]);

        $migration->up();

        $this->assertSame(2, (int) DB::table('subjects')->where('id', $subject->getKey())->value('cycle_number'));
        $this->assertNull(DB::table('subjects')->where('id', $loose->getKey())->value('cycle_number'));
    }

    private function migration(string $file): Migration
    {
        return require database_path("migrations/{$file}");
    }

    private function enroll(Usuario $student, AsignaturaTutoria $tutoring, bool $active): void
    {
        DB::table('inscripcion_tutoria')->insert([
            'fk_asig_tutoria' => $tutoring->getKey(), 'fk_id_usuario' => $student->getKey(),
            'fecha_inscripcion' => today(), 'estado' => $active, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function recordCycle(Usuario $student): ?int
    {
        $value = DB::table('estudiante_periodo')->where('fk_usuario', $student->getKey())->where('fk_periodo', $this->period->getKey())->value('fk_ciclo');

        return $value === null ? null : (int) $value;
    }
}
