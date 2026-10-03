<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('career_id');
            $table->string('code', 30)->nullable();
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->foreign('career_id')->references('id_carrera')->on('carrera')->restrictOnDelete();
            $table->unique(['career_id', 'code']);
        });

        Schema::create('subject_cycle', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('subject_id');
            $table->unsignedInteger('cycle_id');
            $table->timestampsTz();
            $table->foreign('subject_id')->references('id')->on('subjects')->restrictOnDelete();
            $table->foreign('cycle_id')->references('id_ciclo')->on('ciclo')->restrictOnDelete();
            $table->unique(['subject_id', 'cycle_id']);
        });

        Schema::create('career_coordinator', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('career_id');
            $table->timestampTz('assigned_at')->useCurrent();
            $table->foreign('user_id')->references('id_usuario')->on('usuario')->restrictOnDelete();
            $table->foreign('career_id')->references('id_carrera')->on('carrera')->restrictOnDelete();
            $table->unique(['user_id', 'career_id']);
        });

        Schema::create('career_teacher', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('career_id');
            $table->timestampTz('assigned_at')->useCurrent();
            $table->foreign('user_id')->references('id_usuario')->on('usuario')->restrictOnDelete();
            $table->foreign('career_id')->references('id_carrera')->on('carrera')->restrictOnDelete();
            $table->unique(['user_id', 'career_id']);
        });

        if (! Schema::hasTable('ciclo_periodo')) {
            Schema::create('ciclo_periodo', function (Blueprint $table): void {
                $table->unsignedInteger('fk_ciclo');
                $table->unsignedInteger('fk_periodo');
                $table->boolean('estado')->default(true);
                $table->timestampsTz();
                $table->primary(['fk_ciclo', 'fk_periodo']);
                $table->foreign('fk_ciclo')->references('id_ciclo')->on('ciclo')->restrictOnDelete();
                $table->foreign('fk_periodo')->references('id_periodo')->on('periodo_academico')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('asignatura_tutoria')) {
            Schema::create('asignatura_tutoria', function (Blueprint $table): void {
                $table->increments('id_asig_tutoria');
                $table->unsignedInteger('fk_ciclo');
                $table->unsignedInteger('fk_periodo');
                $table->unsignedInteger('fk_modalidad');
                $table->unsignedInteger('fk_paralelo');
                $table->unsignedInteger('fk_docente')->nullable();
                $table->string('nombre', 150);
                $table->boolean('estado')->default(true);
                $table->timestampsTz();
                $table->foreign('fk_ciclo')->references('id_ciclo')->on('ciclo')->restrictOnDelete();
                $table->foreign('fk_periodo')->references('id_periodo')->on('periodo_academico')->restrictOnDelete();
                $table->foreign('fk_modalidad')->references('id_modalidad')->on('modalidad')->restrictOnDelete();
                $table->foreign('fk_paralelo')->references('id_paralelo')->on('paralelo')->restrictOnDelete();
                $table->foreign('fk_docente')->references('id_usuario')->on('usuario')->restrictOnDelete();
            });
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE asignatura_tutoria ALTER COLUMN fk_docente DROP NOT NULL');
        } else {
            Schema::table('asignatura_tutoria', function (Blueprint $table): void {
                $table->unsignedInteger('fk_docente')->nullable()->change();
            });
        }

        if (! Schema::hasColumn('asignatura_tutoria', 'subject_id')) {
            Schema::table('asignatura_tutoria', function (Blueprint $table): void {
                $table->unsignedInteger('subject_id')->nullable();
                $table->foreign('subject_id')->references('id')->on('subjects')->restrictOnDelete();
                $table->unique(['subject_id', 'fk_ciclo', 'fk_periodo'], 'asig_tutoria_subject_cycle_period_unique');
            });
        }

        if (! Schema::hasTable('horario')) {
            Schema::create('horario', function (Blueprint $table): void {
                $table->increments('id_horario');
                $table->unsignedInteger('fk_asig_tutoria');
                $table->string('dia_semana', 20);
                $table->time('hora_inicio');
                $table->time('hora_fin');
                $table->boolean('estado')->default(true);
                $table->timestampsTz();
                $table->foreign('fk_asig_tutoria')->references('id_asig_tutoria')->on('asignatura_tutoria')->restrictOnDelete();
            });
        }

        if (! Schema::hasColumn('horario', 'room')) {
            Schema::table('horario', fn (Blueprint $table) => $table->string('room', 100)->nullable());
        }

        if (! Schema::hasTable('inscripcion_tutoria')) {
            Schema::create('inscripcion_tutoria', function (Blueprint $table): void {
                $table->increments('id_inscripcion');
                $table->unsignedInteger('fk_asig_tutoria');
                $table->unsignedInteger('fk_id_usuario');
                $table->date('fecha_inscripcion');
                $table->boolean('estado')->default(true);
                $table->timestampsTz();
                $table->foreign('fk_asig_tutoria')->references('id_asig_tutoria')->on('asignatura_tutoria')->restrictOnDelete();
                $table->foreign('fk_id_usuario')->references('id_usuario')->on('usuario')->restrictOnDelete();
                $table->unique(['fk_asig_tutoria', 'fk_id_usuario']);
            });
        }

        if (! Schema::hasTable('asistencia')) {
            Schema::create('asistencia', function (Blueprint $table): void {
                $table->increments('id_asistencia');
                $table->unsignedInteger('fk_inscripcion');
                $table->unsignedInteger('fk_id_usuario');
                $table->date('fecha');
                $table->boolean('estado_asistencia');
                $table->timestampsTz();
                $table->foreign('fk_inscripcion')->references('id_inscripcion')->on('inscripcion_tutoria')->restrictOnDelete();
                $table->foreign('fk_id_usuario')->references('id_usuario')->on('usuario')->restrictOnDelete();
                $table->unique(['fk_inscripcion', 'fecha']);
            });
        }

        if (! Schema::hasTable('reporte')) {
            Schema::create('reporte', function (Blueprint $table): void {
                $table->increments('id_reporte');
                $table->unsignedInteger('fk_asig_tutoria');
                $table->string('tipo_reporte', 100);
                $table->unsignedInteger('fk_id_usuario');
                $table->timestampTz('fecha_generacion');
                $table->timestampsTz();
                $table->foreign('fk_asig_tutoria')->references('id_asig_tutoria')->on('asignatura_tutoria')->restrictOnDelete();
                $table->foreign('fk_id_usuario')->references('id_usuario')->on('usuario')->restrictOnDelete();
            });
        }

        if (! Schema::hasColumn('reporte', 'content')) {
            Schema::table('reporte', fn (Blueprint $table) => $table->text('content')->nullable());
        }

        if (DB::getDriverName() === 'pgsql') {
            $this->updatePostgresRoleValidation();

            // The baseline's unconditional unique constraint prevents replacing
            // disabled schedules while keeping their history.
            DB::statement('ALTER TABLE horario DROP CONSTRAINT IF EXISTS horario_unique');

            $this->addPostgresCheck('subjects', 'subjects_code_not_empty', "btrim(code) <> ''");
            $this->addPostgresCheck('subjects', 'subjects_name_not_empty', "btrim(name) <> ''");
            $this->addPostgresCheck('asignatura_tutoria', 'asignatura_tutoria_nombre_no_vacio', "btrim(nombre) <> ''");
            $this->addPostgresCheck('horario', 'horario_dia_valido', "dia_semana IN ('lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo')");
            $this->addPostgresCheck('horario', 'horario_horas_validas', 'hora_fin > hora_inicio');
            $this->addPostgresCheck('horario', 'schedule_room_not_empty', "room IS NULL OR btrim(room) <> ''");
        }

        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX schedule_active_slot_unique ON horario (fk_asig_tutoria, dia_semana, hora_inicio) WHERE estado = true');
        }
    }

    private function updatePostgresRoleValidation(): void
    {
        // Existing baseline triggers call this function. The earlier role
        // migration removed usuario.rol, so validate against the role pivot.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.fn_validar_rol_usuario()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                usuario_id integer;
            BEGIN
                usuario_id := NULLIF(to_jsonb(NEW) ->> TG_ARGV[0], '')::integer;
                IF usuario_id IS NULL THEN
                    RETURN NEW;
                END IF;

                IF NOT EXISTS (
                    SELECT 1
                    FROM public.role_user ru
                    JOIN public.roles r ON r.id = ru.role_id
                    WHERE ru.user_id = usuario_id AND r.slug = TG_ARGV[1]
                ) THEN
                    RAISE EXCEPTION 'El usuario % debe tener el rol %', usuario_id, TG_ARGV[1];
                END IF;

                RETURN NEW;
            END;
            $$;
            SQL);
    }

    private function addPostgresCheck(string $table, string $name, string $expression): void
    {
        $existing = DB::selectOne(
            'SELECT 1 FROM pg_constraint WHERE conrelid = ?::regclass AND conname = ?',
            [$table, $name],
        );

        if ($existing === null) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('El esquema de tutorías puede contener datos del baseline y no admite rollback automático.');
    }
};
