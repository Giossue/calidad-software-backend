<?php

namespace Tests\Feature\Migrations;

use App\Models\InscripcionTutoria;
use App\Models\Paralelo;
use App\Models\Usuario;
use Tests\Feature\Api\V1\Tutoring\TutoringTestCase;

class StudentCycleMigrationTest extends TutoringTestCase
{
    public function test_existing_students_get_the_cycle_of_their_tutoring_or_of_an_unambiguous_section(): void
    {
        $enrolled = Usuario::factory()->withRole('estudiante')->create(['fk_carrera' => $this->career->getKey()]);
        InscripcionTutoria::query()->create(['fk_asig_tutoria' => $this->createTutoring()->getKey(), 'fk_id_usuario' => $enrolled->getKey(), 'fecha_inscripcion' => today(), 'estado' => true]);

        $sectionB = Paralelo::query()->create(['nombre' => 'B', 'estado' => true]);
        $cycleB = $this->createCycle($this->career, $sectionB, 2);
        $bySection = Usuario::factory()->withRole('estudiante')->create(['fk_carrera' => $this->career->getKey()]);
        $bySection->paralelos()->attach($sectionB, ['fecha_asignacion' => today(), 'estado' => true]);

        // El paralelo "A" lo usan el ciclo 1 y el 3: no se puede deducir el ciclo.
        $this->createCycle($this->career, $this->cycle->paralelo, 3);
        $ambiguous = Usuario::factory()->withRole('estudiante')->create(['fk_carrera' => $this->career->getKey()]);
        $ambiguous->paralelos()->attach($this->cycle->fk_paralelo, ['fecha_asignacion' => today(), 'estado' => true]);

        $migration = require database_path('migrations/2026_10_27_100000_add_cycle_to_usuario_table.php');
        $migration->up();

        $this->assertSame($this->cycle->getKey(), $enrolled->refresh()->fk_ciclo);
        $this->assertSame($cycleB->getKey(), $bySection->refresh()->fk_ciclo);
        $this->assertNull($ambiguous->refresh()->fk_ciclo);
    }
}
