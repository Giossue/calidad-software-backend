<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permite el estado `descartado` en los temas de titulación.
 *
 * Cuando el estudiante propone una alternativa, su propuesta pendiente pasa a
 * `descartado` (sin revisor ni fecha de revisión). Las restricciones de la línea
 * base solo aceptaban pendiente, aprobado y rechazado, así que en PostgreSQL esa
 * actualización fallaba con 23514.
 *
 * Usa nombres sin esquema para respetar el `search_path` (en la aplicación es `public`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            // SQLite no crea estas restricciones (ver la migración de línea base).
            return;
        }

        DB::transaction(function (): void {
            DB::statement('ALTER TABLE tema_titulacion DROP CONSTRAINT IF EXISTS degree_topic_status_valid');
            DB::statement('ALTER TABLE tema_titulacion DROP CONSTRAINT IF EXISTS degree_topic_review_matches_status');
            DB::statement("ALTER TABLE tema_titulacion ADD CONSTRAINT degree_topic_status_valid CHECK (estado IN ('pendiente', 'aprobado', 'rechazado', 'descartado'))");
            DB::statement(<<<'SQL'
                ALTER TABLE tema_titulacion ADD CONSTRAINT degree_topic_review_matches_status CHECK (
                    (estado IN ('pendiente', 'descartado') AND fk_coord_revisor IS NULL AND fecha_revision IS NULL)
                    OR (estado IN ('aprobado', 'rechazado') AND fk_coord_revisor IS NOT NULL AND fecha_revision IS NOT NULL)
                )
                SQL);
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::transaction(function (): void {
            if (DB::table('tema_titulacion')->where('estado', 'descartado')->exists()) {
                throw new RuntimeException('Hay temas descartados: no se puede volver a las restricciones anteriores sin perderlos.');
            }

            DB::statement('ALTER TABLE tema_titulacion DROP CONSTRAINT IF EXISTS degree_topic_status_valid');
            DB::statement('ALTER TABLE tema_titulacion DROP CONSTRAINT IF EXISTS degree_topic_review_matches_status');
            DB::statement("ALTER TABLE tema_titulacion ADD CONSTRAINT degree_topic_status_valid CHECK (estado IN ('pendiente', 'aprobado', 'rechazado'))");
            DB::statement(<<<'SQL'
                ALTER TABLE tema_titulacion ADD CONSTRAINT degree_topic_review_matches_status CHECK (
                    (estado = 'pendiente' AND fk_coord_revisor IS NULL AND fecha_revision IS NULL)
                    OR (estado IN ('aprobado', 'rechazado') AND fk_coord_revisor IS NOT NULL AND fecha_revision IS NOT NULL)
                )
                SQL);
        });
    }
};
