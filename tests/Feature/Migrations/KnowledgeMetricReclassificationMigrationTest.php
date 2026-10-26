<?php

namespace Tests\Feature\Migrations;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Api\V1\Teacher\TeacherTestCase;

class KnowledgeMetricReclassificationMigrationTest extends TeacherTestCase
{
    public function test_metrics_are_reclassified_with_the_official_ranges_and_can_be_reverted(): void
    {
        $metrics = [];
        foreach (['4.50' => ['medium', 'Medio'], '8.00' => ['high', 'Alto']] as $grade => [$key, $label]) {
            $enrollment = $this->enrollment();
            $this->putJson($this->path('/students/'.$enrollment->getKey().'/grades/diagnostic'), ['value' => $grade])->assertOk();
            // Simula una métrica calculada con la escala provisional (Bajo < 4).
            DB::table('metrica_conocimiento')->where('enrollment_id', $enrollment->getKey())->update(['rango' => $key, 'descripcion' => $label]);
            $metrics[$grade] = $enrollment->getKey();
        }

        $migration = require database_path('migrations/2026_10_26_100000_reclassify_knowledge_metrics_with_official_ranges.php');
        $migration->up();

        $this->assertDatabaseHas('metrica_conocimiento', ['enrollment_id' => $metrics['4.50'], 'rango' => 'low', 'descripcion' => 'Bajo']);
        $this->assertDatabaseHas('metrica_conocimiento', ['enrollment_id' => $metrics['8.00'], 'rango' => 'high', 'descripcion' => 'Alto']);

        $migration->down();

        $this->assertDatabaseHas('metrica_conocimiento', ['enrollment_id' => $metrics['4.50'], 'rango' => 'medium', 'descripcion' => 'Medio']);
    }
}
