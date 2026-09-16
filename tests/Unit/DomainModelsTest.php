<?php

namespace Tests\Unit;

use App\Models\ActividadAvance;
use App\Models\AsignacionDocente;
use App\Models\Asistencia;
use App\Models\HorarioTitulacion;
use App\Models\InformeTitulacion;
use App\Models\InscripcionTutoria;
use App\Models\MetricaConocimiento;
use App\Models\Observacion;
use App\Models\ObservacionTitulacion;
use App\Models\Reporte;
use App\Models\TemaTitulacion;
use App\Models\User;
use App\Models\UsuarioParalelo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

class DomainModelsTest extends TestCase
{
    public function test_user_relations_share_the_canonical_auth_model(): void
    {
        $relations = [
            (new ActividadAvance)->teacher(),
            (new AsignacionDocente)->teacher(),
            (new Asistencia)->student(),
            (new HorarioTitulacion)->coordinator(),
            (new InformeTitulacion)->coordinator(),
            (new InscripcionTutoria)->student(),
            (new MetricaConocimiento)->user(),
            (new Observacion)->teacher(),
            (new ObservacionTitulacion)->coordinator(),
            (new Reporte)->generatedBy(),
            (new TemaTitulacion)->student(),
            (new TemaTitulacion)->reviewingCoordinator(),
            (new UsuarioParalelo)->user(),
        ];

        foreach ($relations as $relation) {
            $this->assertInstanceOf(BelongsTo::class, $relation);
            $this->assertInstanceOf(User::class, $relation->getRelated());
        }
    }

    public function test_attendance_casts_its_binary_state_to_boolean(): void
    {
        $attendance = new Asistencia(['estado_asistencia' => 1]);

        $this->assertTrue($attendance->estado_asistencia);
    }
}
