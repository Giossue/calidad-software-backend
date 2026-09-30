<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing PostgreSQL installations already have these domain tables.
        if (! Schema::hasTable('tema')) {
            Schema::create('tema', function (Blueprint $table): void {
                $table->increments('id_tema');
                $table->unsignedInteger('fk_asig_tutoria');
                $table->string('nombre', 150);
                $table->string('descripcion', 255)->nullable();
                $table->boolean('visto')->default(false);
                $table->boolean('estado')->default(true);
                $table->timestampsTz();
                $table->foreign('fk_asig_tutoria')->references('id_asig_tutoria')->on('asignatura_tutoria')->restrictOnDelete();
                $table->unique(['fk_asig_tutoria', 'nombre']);
            });
        }
        if (! Schema::hasTable('actividad')) {
            Schema::create('actividad', function (Blueprint $table): void {
                $table->increments('id_actividad');
                $table->unsignedInteger('fk_tema');
                $table->string('nombre', 150);
                $table->string('duracion', 50);
                $table->boolean('estado')->default(true);
                $table->timestampsTz();
                $table->foreign('fk_tema')->references('id_tema')->on('tema')->restrictOnDelete();
                $table->unique(['fk_tema', 'nombre']);
            });
        }
        if (! Schema::hasTable('metodologia')) {
            Schema::create('metodologia', function (Blueprint $table): void {
                $table->increments('id_metodologia');
                $table->unsignedInteger('fk_actividad');
                $table->string('descripcion', 255);
                $table->boolean('estado')->default(true);
                $table->timestampsTz();
                $table->foreign('fk_actividad')->references('id_actividad')->on('actividad')->restrictOnDelete();
            });
        }
        if (! Schema::hasTable('nota')) {
            Schema::create('nota', function (Blueprint $table): void {
                $table->increments('id_nota');
                $table->unsignedInteger('fk_inscripcion');
                $table->string('tipo', 50);
                $table->decimal('valor', 10, 2);
                $table->date('fecha_registro');
                $table->timestampsTz();
                $table->foreign('fk_inscripcion')->references('id_inscripcion')->on('inscripcion_tutoria')->restrictOnDelete();
            });
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE nota ADD CONSTRAINT teacher_grade_non_negative CHECK (valor >= 0)');
            }
        }
        Schema::table('nota', fn (Blueprint $table) => $table->index(['fk_inscripcion', 'tipo', 'id_nota'], 'teacher_grade_history_index'));

        if (! Schema::hasTable('metrica_conocimiento')) {
            Schema::create('metrica_conocimiento', function (Blueprint $table): void {
                $table->increments('id_metrica');
                $table->unsignedInteger('fk_id_usuario');
                $table->string('descripcion', 255);
                $table->string('rango', 100)->nullable();
                $table->decimal('nota_minima', 10, 2)->nullable();
                $table->decimal('nota_maxima', 10, 2)->nullable();
                $table->string('estado', 50)->nullable();
                $table->timestampsTz();
                $table->foreign('fk_id_usuario')->references('id_usuario')->on('usuario')->restrictOnDelete();
            });
        }
        Schema::table('metrica_conocimiento', function (Blueprint $table): void {
            $table->unsignedInteger('enrollment_id')->nullable()->unique();
            $table->foreign('enrollment_id')->references('id_inscripcion')->on('inscripcion_tutoria')->restrictOnDelete();
        });

        Schema::create('tutoring_sessions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('tutoring_id');
            $table->unsignedInteger('recorded_by');
            $table->date('date');
            $table->boolean('topics_covered')->default(false);
            $table->timestampsTz();
            $table->foreign('tutoring_id')->references('id_asig_tutoria')->on('asignatura_tutoria')->restrictOnDelete();
            $table->foreign('recorded_by')->references('id_usuario')->on('usuario')->restrictOnDelete();
            $table->unique(['tutoring_id', 'date']);
        });
        Schema::create('tutoring_session_topic', function (Blueprint $table): void {
            $table->unsignedInteger('session_id');
            $table->unsignedInteger('topic_id');
            $table->primary(['session_id', 'topic_id']);
            $table->foreign('session_id')->references('id')->on('tutoring_sessions')->restrictOnDelete();
            $table->foreign('topic_id')->references('id_tema')->on('tema')->restrictOnDelete();
        });
        Schema::table('asistencia', function (Blueprint $table): void {
            $table->unsignedInteger('session_id')->nullable()->index();
            $table->foreign('session_id')->references('id')->on('tutoring_sessions')->restrictOnDelete();
        });
        Schema::table('reporte', fn (Blueprint $table) => $table->json('summary')->nullable());
    }

    public function down(): void
    {
        Schema::table('reporte', fn (Blueprint $table) => $table->dropColumn('summary'));
        Schema::table('asistencia', function (Blueprint $table): void {
            $table->dropForeign(['session_id']);
            $table->dropColumn('session_id');
        });
        Schema::dropIfExists('tutoring_session_topic');
        Schema::dropIfExists('tutoring_sessions');
        Schema::table('metrica_conocimiento', function (Blueprint $table): void {
            $table->dropForeign(['enrollment_id']);
            $table->dropColumn('enrollment_id');
        });
        Schema::table('nota', fn (Blueprint $table) => $table->dropIndex('teacher_grade_history_index'));
        // Preserve baseline tables and their academic history.
    }
};
