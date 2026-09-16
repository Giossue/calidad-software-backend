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
use App\Models\Usuario;
use App\Models\UsuarioParalelo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

class DomainModelsTest extends TestCase
{
    public function test_las_relaciones_de_usuario_comparten_el_modelo_autenticable_canonico(): void
    {
        $relations = [
            (new ActividadAvance)->docente(),
            (new AsignacionDocente)->docente(),
            (new Asistencia)->estudiante(),
            (new HorarioTitulacion)->coordinador(),
            (new InformeTitulacion)->coordinador(),
            (new InscripcionTutoria)->estudiante(),
            (new MetricaConocimiento)->usuario(),
            (new Observacion)->docente(),
            (new ObservacionTitulacion)->coordinador(),
            (new Reporte)->generadoPor(),
            (new TemaTitulacion)->estudiante(),
            (new TemaTitulacion)->coordinadorRevisor(),
            (new UsuarioParalelo)->usuario(),
        ];

        foreach ($relations as $relation) {
            $this->assertInstanceOf(BelongsTo::class, $relation);
            $this->assertInstanceOf(Usuario::class, $relation->getRelated());
        }
    }

    public function test_attendance_casts_its_binary_state_to_boolean(): void
    {
        $attendance = new Asistencia(['estado_asistencia' => 1]);

        $this->assertTrue($attendance->estado_asistencia);
    }
}
