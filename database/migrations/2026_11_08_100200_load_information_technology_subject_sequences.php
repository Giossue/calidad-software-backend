<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Carga las secuencias de la malla curricular de Tecnologías de la Información
 * (RCU-009-2022-044). Las asignaturas se buscan por código dentro de la carrera;
 * las que no tienen código, por nombre. Lo que no exista se omite.
 */
return new class extends Migration
{
    /** [previa, siguiente]: códigos o nombres de la malla. */
    private const SEQUENCES = [
        ['TI-B1-001', 'TI-B2-006'],
        ['TI-B1-002', 'TI-B2-009'],
        ['TI-B1-004', 'TI-B2-009'],
        ['TI-B2-006', 'TI-B3-011'],
        ['TI-B2-007', 'TI-B3-012'],
        ['TI-B2-008', 'TI-B3-013'],
        ['TI-B2-009', 'TI-B3-014'],
        ['TI-B3-011', 'TI-P4-016'],
        ['TI-B3-012', 'TI-P4-019'],
        ['TI-P4-017', 'TI-P5-022'],
        ['TI-P4-018', 'TI-P5-023'],
        ['TI-P5-022', 'TI-P6-027'],
        ['TI-P5-023', 'TI-P6-028'],
        ['TI-P5-024', 'TI-P6-029'],
        ['TI-P6-026', 'TI-P7-031'],
        ['TI-P6-027', 'TI-P7-033'],
        ['TI-P6-028', 'TI-P7-033'],
        ['TI-P6-029', 'TI-P7-033'],
        ['TI-P7-031', 'TI-IC8-034'],
        ['TI-P7-031', 'TI-IC8-035'],
        ['PRACTICAS DE SERVICIO COMUNITARIO', 'TRABAJO DE INTEGRACION CURRICULAR'],
    ];

    public function up(): void
    {
        $careerIds = DB::table('carrera')->get(['id_carrera', 'nombre'])
            ->filter(fn (object $career): bool => self::normalize($career->nombre) === 'TECNOLOGIAS DE LA INFORMACION')
            ->pluck('id_carrera');

        foreach ($careerIds as $careerId) {
            $subjects = DB::table('subjects')->where('career_id', $careerId)->get(['id', 'code', 'name']);
            $find = fn (string $key): ?int => $subjects->first(
                fn (object $subject): bool => ($subject->code !== null && strtoupper($subject->code) === $key) || self::normalize($subject->name) === $key
            )?->id;

            foreach (self::SEQUENCES as [$from, $to]) {
                $subjectId = $find($from);
                $nextId = $find($to);
                if ($subjectId === null || $nextId === null) {
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

    private static function normalize(string $value): string
    {
        $value = Normalizer::normalize(trim($value), Normalizer::FORM_D) ?: $value;

        return mb_strtoupper((string) preg_replace('/\p{Mn}/u', '', $value));
    }
};
