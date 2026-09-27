<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Malla curricular oficial de la carrera de Software (UEB), modalidad
     * presencial, 8 ciclos. Fuente: página oficial de la carrera y el PDF de
     * la malla enlazado en ella (consultados el 27 de septiembre de 2026).
     *
     * Los 3 componentes que la malla no publica con código (prácticas
     * preprofesionales, prácticas de servicio comunitario y el trabajo de
     * titulación e integración curricular) se excluyen a propósito: la tabla
     * `subjects` exige un código no vacío y este catálogo no inventa códigos.
     *
     * @var array<int, array<int, array{code: string, name: string}>>
     */
    private const SUBJECTS_BY_CYCLE = [
        1 => [
            ['code' => 'SW-B1-001', 'name' => 'Algoritmos y lógica de programación'],
            ['code' => 'SW-B1-002', 'name' => 'Álgebra lineal'],
            ['code' => 'SW-B1-003', 'name' => 'Estructuras discretas'],
            ['code' => 'SW-B1-004', 'name' => 'Cálculo I'],
            ['code' => 'SW-B1-005', 'name' => 'Lenguaje y comunicación'],
        ],
        2 => [
            ['code' => 'SW-B2-006', 'name' => 'Programación orientada a objetos'],
            ['code' => 'SW-B2-007', 'name' => 'Arquitectura de computadores'],
            ['code' => 'SW-B2-008', 'name' => 'Fundamentos de física para ingeniería'],
            ['code' => 'SW-B2-009', 'name' => 'Cálculo II'],
            ['code' => 'SW-B2-010', 'name' => 'Realidad nacional y diversidad cultural'],
        ],
        3 => [
            ['code' => 'SW-B3-011', 'name' => 'Estructura de datos'],
            ['code' => 'SW-B3-012', 'name' => 'Ingeniería de requerimientos'],
            ['code' => 'SW-B3-013', 'name' => 'Sistemas de información'],
            ['code' => 'SW-B3-014', 'name' => 'Cálculo III'],
            ['code' => 'SW-B3-015', 'name' => 'Estadística y probabilidades'],
            ['code' => 'SW-B3-016', 'name' => 'Trabajo en equipo y comunicación eficaz'],
        ],
        4 => [
            ['code' => 'SW-P4-017', 'name' => 'Programación web I'],
            ['code' => 'SW-P4-018', 'name' => 'Modelamiento y diseño de software'],
            ['code' => 'SW-P4-019', 'name' => 'Base de datos'],
            ['code' => 'SW-P4-020', 'name' => 'Sistemas operativos'],
            ['code' => 'SW-P4-021', 'name' => 'Métodos numéricos'],
            ['code' => 'SW-P4-022', 'name' => 'Sostenibilidad ambiental'],
        ],
        5 => [
            ['code' => 'SW-P5-023', 'name' => 'Programación web II'],
            ['code' => 'SW-P5-024', 'name' => 'Arquitectura del software'],
            ['code' => 'SW-P5-025', 'name' => 'Administración de base de datos'],
            ['code' => 'SW-P5-026', 'name' => 'Investigación de operaciones'],
            ['code' => 'SW-P5-027', 'name' => 'Epistemología y metodología de investigación'],
        ],
        6 => [
            ['code' => 'SW-P6-028', 'name' => 'Programación móvil'],
            ['code' => 'SW-P6-029', 'name' => 'Mantenimiento y configuración de software'],
            ['code' => 'SW-P6-030', 'name' => 'Interacción hombre máquina'],
            ['code' => 'SW-P6-031', 'name' => 'Fundamentos de redes y conectividad'],
            ['code' => 'SW-P6-032', 'name' => 'Simulación'],
            ['code' => 'SW-P6-033', 'name' => 'Liderazgo y emprendimiento'],
        ],
        7 => [
            ['code' => 'SW-P7-034', 'name' => 'Aplicaciones distribuidas'],
            ['code' => 'SW-P7-035', 'name' => 'Seguridad del software'],
            ['code' => 'SW-P7-036', 'name' => 'Redes de datos'],
            ['code' => 'SW-P7-037', 'name' => 'Inteligencia artificial'],
        ],
        8 => [
            ['code' => 'SW-IC8-038', 'name' => 'Gestión de las tecnologías de la información'],
            ['code' => 'SW-IC8-039', 'name' => 'Calidad de software'],
            ['code' => 'SW-IC8-040', 'name' => 'Deontología informática'],
        ],
    ];

    /**
     * Esta migración NO crea la carrera "Software": en este sistema las
     * carreras se crean desde el panel de administración (no hay seeder ni
     * migración que las genere), así que asumir que puede crearla aquí
     * contaminaría entornos nuevos (tests, `migrate:fresh` local) con una
     * carrera, facultad y modalidad que esos entornos no esperan; varias
     * pruebas del catálogo académico verifican conteos exactos de filas y
     * fallarían. Si la carrera todavía no existe en el entorno donde se
     * ejecuta esta migración, no hace nada: hay que crear "Software" desde
     * la UI primero y volver a correr `php artisan migrate` después.
     *
     * Cuando la carrera ya existe (p. ej. producción, donde hoy tiene más
     * ciclos que los 8 de la malla porque algunos se repiten por paralelo,
     * ver 2026_09_24_110000_add_paralelo_to_ciclo_table.php), no se tocan la
     * carrera ni los ciclos existentes: la asignatura de cada ciclo de la
     * malla se enlaza a TODAS las filas de `ciclo` que ya compartan ese
     * número, sin importar cuántos paralelos tengan.
     */
    public function up(): void
    {
        $careerId = DB::table('carrera')->where('nombre', 'Software')->value('id_carrera');

        if ($careerId === null) {
            return;
        }

        $now = now();

        foreach (self::SUBJECTS_BY_CYCLE as $cycleNumber => $subjects) {
            $cycleIds = DB::table('ciclo')
                ->where('fk_carrera', $careerId)
                ->where('numero', $cycleNumber)
                ->pluck('id_ciclo');

            if ($cycleIds->isEmpty()) {
                $cycleIds = collect([
                    DB::table('ciclo')->insertGetId([
                        'fk_carrera' => $careerId,
                        'nombre' => "Ciclo {$cycleNumber}",
                        'numero' => $cycleNumber,
                        'fk_paralelo' => null,
                        'estado' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]),
                ]);
            }

            foreach ($subjects as $subject) {
                $subjectId = DB::table('subjects')
                    ->where('career_id', $careerId)
                    ->where('code', $subject['code'])
                    ->value('id');

                if ($subjectId === null) {
                    $subjectId = DB::table('subjects')->insertGetId([
                        'career_id' => $careerId,
                        'code' => $subject['code'],
                        'name' => $subject['name'],
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                foreach ($cycleIds as $cycleId) {
                    $alreadyLinked = DB::table('subject_cycle')
                        ->where('subject_id', $subjectId)
                        ->where('cycle_id', $cycleId)
                        ->exists();

                    if (! $alreadyLinked) {
                        DB::table('subject_cycle')->insert([
                            'subject_id' => $subjectId,
                            'cycle_id' => $cycleId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Esta migración siembra el catálogo oficial de asignaturas de Software y no admite rollback automático.');
    }
};
