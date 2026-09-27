<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // Fresh SQLite environments already use text for both fields.
            if (! in_array(Schema::getColumnType('tema_titulacion', 'estado'), ['varchar', 'text'], true)) {
                throw new RuntimeException('El estado de titulación de SQLite necesita revisión manual antes de convertir datos históricos.');
            }

            return;
        }

        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('La compatibilidad de titulación requiere PostgreSQL o SQLite.');
        }

        DB::transaction(function (): void {
            // Keep the validation and type change on the same locked snapshot.
            DB::statement('LOCK TABLE public.tema_titulacion, public.asignacion_docente, public.observacion_titulacion IN ACCESS EXCLUSIVE MODE');
            $type = DB::table('information_schema.columns')
                ->where('table_schema', 'public')->where('table_name', 'tema_titulacion')
                ->where('column_name', 'estado')->value('data_type');

            if ($type === 'boolean') {
                $this->assertLegacyStatesAreUnambiguous();
                DB::statement('ALTER TABLE public.tema_titulacion ALTER COLUMN estado DROP DEFAULT');
                DB::statement(<<<'SQL'
                    ALTER TABLE public.tema_titulacion
                    ALTER COLUMN estado TYPE varchar(50)
                    USING (
                        CASE
                            WHEN fecha_revision IS NULL THEN 'pendiente'
                            WHEN estado IS TRUE THEN 'aprobado'
                            ELSE 'rechazado'
                        END
                    )
                    SQL);
            } elseif (! in_array($type, ['character varying', 'text'], true)) {
                throw new RuntimeException('El tipo de estado de titulación no es compatible con una conversión segura.');
            }

            $this->assertTextStatesAreConsistent();
            DB::statement("ALTER TABLE public.tema_titulacion ALTER COLUMN estado SET DEFAULT 'pendiente'");
            DB::statement('ALTER TABLE public.tema_titulacion ALTER COLUMN estado SET NOT NULL');
            $this->addCheck('degree_topic_status_valid', "estado IN ('pendiente', 'aprobado', 'rechazado')");
            $this->addCheck('degree_topic_review_matches_status', <<<'SQL'
                (estado = 'pendiente' AND fk_coord_revisor IS NULL AND fecha_revision IS NULL)
                OR (estado IN ('aprobado', 'rechazado') AND fk_coord_revisor IS NOT NULL AND fecha_revision IS NOT NULL)
                SQL);

            // Widening keeps existing text, nullability, defaults and constraints.
            DB::statement('ALTER TABLE public.observacion_titulacion ALTER COLUMN descripcion TYPE text');
            $this->allowAdministratorForDegreeReview();
        });
    }

    private function assertLegacyStatesAreUnambiguous(): void
    {
        $ambiguous = DB::selectOne(<<<'SQL'
            SELECT count(*) AS total
            FROM public.tema_titulacion topic
            WHERE topic.estado IS NULL
               OR ((topic.fk_coord_revisor IS NULL) <> (topic.fecha_revision IS NULL))
               OR (topic.estado IS FALSE AND topic.fecha_revision IS NULL)
               OR (
                    topic.fecha_revision IS NOT NULL AND topic.estado IS TRUE
                    AND (
                        (SELECT count(*) FROM public.asignacion_docente a
                         WHERE a.fk_tema_tit = topic.id_tema_tit AND a.estado AND a.rol = 'tutor') <> 1
                        OR NOT EXISTS (
                            SELECT 1 FROM public.asignacion_docente a
                            WHERE a.fk_tema_tit = topic.id_tema_tit AND a.estado AND a.rol = 'par_academico'
                        )
                        OR EXISTS (
                            SELECT 1 FROM public.asignacion_docente tutor
                            JOIN public.asignacion_docente peer
                              ON peer.fk_tema_tit = tutor.fk_tema_tit AND peer.fk_id_usuario = tutor.fk_id_usuario
                            WHERE tutor.fk_tema_tit = topic.id_tema_tit
                              AND tutor.estado AND peer.estado
                              AND tutor.rol = 'tutor' AND peer.rol = 'par_academico'
                        )
                    )
               )
               OR (
                    (topic.fecha_revision IS NULL OR topic.estado IS FALSE)
                    AND EXISTS (
                        SELECT 1 FROM public.asignacion_docente a
                        WHERE a.fk_tema_tit = topic.id_tema_tit AND a.estado
                    )
               )
            SQL);

        if ($ambiguous !== null && (int) $ambiguous->total > 0) {
            throw new RuntimeException('Hay temas históricos de titulación ambiguos; revise sus datos de revisión y asignaciones antes de migrar. No se convirtió ningún estado.');
        }
    }

    private function assertTextStatesAreConsistent(): void
    {
        if (DB::table('tema_titulacion')->whereRaw(<<<'SQL'
            estado IS NULL
            OR estado NOT IN ('pendiente', 'aprobado', 'rechazado')
            OR (estado = 'pendiente' AND (fk_coord_revisor IS NOT NULL OR fecha_revision IS NOT NULL))
            OR (estado IN ('aprobado', 'rechazado') AND (fk_coord_revisor IS NULL OR fecha_revision IS NULL))
            SQL)->exists()) {
            throw new RuntimeException('Hay estados de titulación desconocidos o incompatibles con los datos de revisión; se requiere revisión manual.');
        }
    }

    private function addCheck(string $name, string $expression): void
    {
        if (DB::selectOne(
            'SELECT 1 FROM pg_constraint WHERE conrelid = ?::regclass AND conname = ?',
            ['public.tema_titulacion', $name],
        ) === null) {
            DB::statement("ALTER TABLE public.tema_titulacion ADD CONSTRAINT {$name} CHECK ({$expression})");
        }
    }

    private function allowAdministratorForDegreeReview(): void
    {
        // The generic student/teacher/other coordinator role function is untouched.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.validate_degree_coordinator_role()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                target_user_id integer;
            BEGIN
                IF TG_TABLE_SCHEMA <> 'public' OR NOT (
                    (TG_TABLE_NAME = 'tema_titulacion' AND TG_ARGV[0] = 'fk_coord_revisor')
                    OR (TG_TABLE_NAME = 'observacion_titulacion' AND TG_ARGV[0] = 'fk_coord_tit')
                ) THEN
                    RAISE EXCEPTION 'La validación de coordinación no corresponde a esta operación';
                END IF;

                target_user_id := NULLIF(to_jsonb(NEW) ->> TG_ARGV[0], '')::integer;
                IF target_user_id IS NULL THEN
                    RETURN NEW;
                END IF;

                IF NOT EXISTS (
                    SELECT 1 FROM public.role_user ru
                    JOIN public.roles r ON r.id = ru.role_id
                    WHERE ru.user_id = target_user_id AND r.slug IN ('coordinador_titulacion', 'administrador')
                ) THEN
                    RAISE EXCEPTION 'El usuario % debe ser coordinador de titulación o administrador', target_user_id;
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE OR REPLACE TRIGGER rol_coordinador_revisor
            BEFORE INSERT OR UPDATE OF fk_coord_revisor ON public.tema_titulacion
            FOR EACH ROW EXECUTE FUNCTION public.validate_degree_coordinator_role('fk_coord_revisor');

            CREATE OR REPLACE TRIGGER rol_coordinador_observacion
            BEFORE INSERT OR UPDATE OF fk_coord_tit ON public.observacion_titulacion
            FOR EACH ROW EXECUTE FUNCTION public.validate_degree_coordinator_role('fk_coord_tit');
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('La conversión de estados de titulación y observaciones no admite rollback automático sin pérdida de información.');
    }
};
