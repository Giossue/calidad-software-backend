<?php

namespace Tests\Feature\Migrations;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Api\V1\Tutoring\TutoringTestCase;

/** Las inscripciones registran su número de matrícula; las existentes quedan en 1.ª. */
class EnrollmentNumberMigrationTest extends TutoringTestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_11_15_130000_add_numero_matricula_to_inscripcion_tutoria_table.php';

    public function test_existing_enrollments_become_first_matricula_and_down_removes_the_column(): void
    {
        $migration = require database_path(self::MIGRATION);
        $migration->down();
        $this->assertFalse(Schema::hasColumn('inscripcion_tutoria', 'numero_matricula'));

        $tutoring = $this->createTutoring();
        $student = Usuario::factory()->student()->create();
        DB::table('inscripcion_tutoria')->insert([
            'fk_asig_tutoria' => $tutoring->getKey(), 'fk_id_usuario' => $student->getKey(), 'fecha_inscripcion' => today(), 'estado' => true,
        ]);

        $migration->up();

        $this->assertTrue(Schema::hasColumn('inscripcion_tutoria', 'numero_matricula'));
        $this->assertSame([1], DB::table('inscripcion_tutoria')->pluck('numero_matricula')->map(fn ($value): int => (int) $value)->all());

        DB::table('inscripcion_tutoria')->insert([
            'fk_asig_tutoria' => $tutoring->getKey(), 'fk_id_usuario' => Usuario::factory()->student()->create()->getKey(), 'fecha_inscripcion' => today(), 'estado' => true,
        ]);
        $this->assertSame([1, 1], DB::table('inscripcion_tutoria')->orderBy('id_inscripcion')->pluck('numero_matricula')->map(fn ($value): int => (int) $value)->all());
    }
}
