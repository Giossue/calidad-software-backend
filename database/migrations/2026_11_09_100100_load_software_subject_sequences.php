<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Carga las secuencias de la malla curricular de Software (8 ciclos, modalidad
 * presencial). Cada asignatura se busca dentro de la carrera por su código y, si
 * no lo tiene, por su nombre. Lo que no exista se omite.
 */
return new class extends Migration
{
    /** Código => nombre de cada asignatura de la malla que participa en una secuencia. */
    private const SUBJECTS = [
        'SW-B1-001' => 'ALGORITMOS Y LOGICA DE PROGRAMACION',
        'SW-B1-002' => 'ALGEBRA LINEAL',
        'SW-B1-003' => 'ESTRUCTURAS DISCRETAS',
        'SW-B1-004' => 'CALCULO I',
        'SW-B2-006' => 'PROGRAMACION ORIENTADA A OBJETOS',
        'SW-B2-008' => 'FUNDAMENTOS DE FISICA PARA INGENIERIA',
        'SW-B2-009' => 'CALCULO II',
        'SW-B3-011' => 'ESTRUCTURA DE DATOS',
        'SW-B3-012' => 'INGENIERIA DE REQUERIMIENTOS',
        'SW-B3-013' => 'SISTEMAS DE INFORMACION',
        'SW-B3-014' => 'CALCULO III',
        'SW-B3-015' => 'ESTADISTICA Y PROBABILIDADES',
        'SW-P4-017' => 'PROGRAMACION WEB I',
        'SW-P4-018' => 'MODELAMIENTO Y DISENO DE SOFTWARE',
        'SW-P4-019' => 'BASE DE DATOS',
        'SW-P4-020' => 'SISTEMAS OPERATIVOS',
        'SW-P4-021' => 'METODOS NUMERICOS',
        'SW-P5-023' => 'PROGRAMACION WEB II',
        'SW-P5-024' => 'ARQUITECTURA DEL SOFTWARE',
        'SW-P5-025' => 'ADMINISTRACION DE BASE DE DATOS',
        'SW-P5-026' => 'INVESTIGACION DE OPERACIONES',
        'SW-P6-028' => 'PROGRAMACION MOVIL',
        'SW-P6-029' => 'MANTENIMIENTO Y CONFIGURACION DE SOFTWARE',
        'SW-P6-030' => 'INTERACCION HOMBRE MAQUINA',
        'SW-P6-031' => 'FUNDAMENTOS DE REDES Y CONECTIVIDAD',
        'SW-P6-032' => 'SIMULACION',
        'SW-P7-034' => 'APLICACIONES DISTRIBUIDAS',
        'SW-P7-035' => 'SEGURIDAD DEL SOFTWARE',
        'SW-P7-036' => 'REDES DE DATOS',
        'SW-P7-037' => 'INTELIGENCIA ARTIFICIAL',
        'SW-IC8-038' => 'GESTION DE LAS TECNOLOGIAS DE LA INFORMACION',
        'SW-IC8-039' => 'CALIDAD DE SOFTWARE',
        'PRACTICAS PREPROFESIONALES' => 'PRACTICAS PREPROFESIONALES',
        'PRACTICAS DE SERVICIO COMUNITARIO' => 'PRACTICAS DE SERVICIO COMUNITARIO',
    ];

    /** [previa, siguiente], según las flechas de la malla. */
    private const SEQUENCES = [
        ['SW-B1-001', 'SW-B2-006'],
        ['SW-B1-002', 'SW-B2-008'],
        ['SW-B1-003', 'SW-B2-008'],
        ['SW-B1-004', 'SW-B2-009'],
        ['SW-B2-006', 'SW-B3-011'],
        ['SW-B2-009', 'SW-B3-014'],
        ['SW-B2-009', 'SW-B3-015'],
        ['SW-B3-011', 'SW-P4-017'],
        ['SW-B3-012', 'SW-P4-018'],
        ['SW-B3-013', 'SW-P4-020'],
        ['SW-B3-014', 'SW-P4-021'],
        ['SW-P4-017', 'SW-P5-023'],
        ['SW-P4-018', 'SW-P5-024'],
        ['SW-P4-019', 'SW-P5-025'],
        ['SW-P4-021', 'SW-P5-026'],
        ['SW-P5-023', 'SW-P6-028'],
        ['SW-P5-023', 'SW-P6-030'],
        ['SW-P5-024', 'SW-P6-029'],
        ['SW-P5-026', 'SW-P6-032'],
        ['SW-P6-028', 'SW-P7-034'],
        ['SW-P6-029', 'SW-P7-034'],
        ['SW-P6-029', 'SW-P7-035'],
        ['SW-P6-029', 'SW-P7-036'],
        ['SW-P6-031', 'SW-P7-036'],
        ['SW-P6-032', 'SW-P7-037'],
        ['SW-P7-035', 'SW-IC8-038'],
        ['SW-P7-035', 'SW-IC8-039'],
        ['PRACTICAS PREPROFESIONALES', 'PRACTICAS DE SERVICIO COMUNITARIO'],
    ];

    public function up(): void
    {
        $careerIds = DB::table('carrera')->get(['id_carrera', 'nombre'])
            ->filter(fn (object $career): bool => self::normalize($career->nombre) === 'SOFTWARE')
            ->pluck('id_carrera');

        foreach ($careerIds as $careerId) {
            $subjects = DB::table('subjects')->where('career_id', $careerId)->get(['id', 'code', 'name']);
            $find = fn (string $code): ?int => ($subjects->first(fn (object $subject): bool => $subject->code !== null && strtoupper(trim($subject->code)) === $code)
                ?? $subjects->first(fn (object $subject): bool => self::normalize($subject->name) === self::SUBJECTS[$code]))?->id;

            foreach (self::SEQUENCES as [$from, $to]) {
                $subjectId = $find($from);
                $nextId = $find($to);
                if ($subjectId === null || $nextId === null || $subjectId === $nextId) {
                    continue;
                }
                DB::table('subject_sequences')->insertOrIgnore([
                    'subject_id' => $subjectId,
                    'next_subject_id' => $nextId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Las secuencias se pueden editar después; no se revierten aquí.
    }

    /** Mayúsculas, sin tildes ni espacios repetidos. */
    private static function normalize(string $value): string
    {
        $value = Normalizer::normalize(trim($value), Normalizer::FORM_D) ?: $value;

        return mb_strtoupper((string) preg_replace(['/\p{Mn}/u', '/\s+/u'], ['', ' '], $value));
    }
};
