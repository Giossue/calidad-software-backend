<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            throw new RuntimeException('La compatibilidad de seguimiento requiere PostgreSQL o SQLite.');
        }

        // These tables may belong to the institutional baseline. Never recreate
        // them or replace their foreign keys, checks, timestamps or triggers.
        if (! Schema::hasTable('ficha_seguimiento')) {
            $this->createTrackingSheet();
        }

        if (! Schema::hasTable('actividad_avance')) {
            $this->createProgressActivity();
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE public.actividad_avance ALTER COLUMN descripcion TYPE text');
        }

        if (! Schema::hasTable('informe_titulacion')) {
            Schema::create('informe_titulacion', function (Blueprint $table): void {
                $table->increments('id_informe');
                $table->unsignedInteger('fk_ficha');
                $table->unsignedInteger('fk_coord_tit');
                $table->date('fecha_generacion')->default(DB::raw('CURRENT_DATE'));
                $table->text('observaciones_finales')->nullable();
                $table->boolean('estado')->default(true);
                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();
                $table->foreign('fk_ficha')->references('id_ficha')->on('ficha_seguimiento')->restrictOnDelete();
                $table->foreign('fk_coord_tit')->references('id_usuario')->on('usuario')->restrictOnDelete();
            });
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE public.informe_titulacion ALTER COLUMN observaciones_finales TYPE text');
        }
    }

    private function createTrackingSheet(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite cannot add CHECK constraints after creating the table.
            DB::statement(<<<'SQL'
                CREATE TABLE ficha_seguimiento (
                    id_ficha INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    fk_tema_tit INTEGER NOT NULL UNIQUE,
                    fecha_apertura DATE NOT NULL DEFAULT CURRENT_DATE,
                    porcentaje_avance NUMERIC(5, 2) NOT NULL DEFAULT 0,
                    estado VARCHAR(50) NOT NULL DEFAULT 'en_progreso',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT ficha_porcentaje_valido CHECK (porcentaje_avance >= 0 AND porcentaje_avance <= 100),
                    CONSTRAINT ficha_estado_no_vacio CHECK (trim(estado) <> ''),
                    FOREIGN KEY (fk_tema_tit) REFERENCES tema_titulacion(id_tema_tit) ON DELETE RESTRICT
                )
                SQL);

            return;
        }

        Schema::create('ficha_seguimiento', function (Blueprint $table): void {
            $table->increments('id_ficha');
            $table->unsignedInteger('fk_tema_tit')->unique();
            $table->date('fecha_apertura')->default(DB::raw('CURRENT_DATE'));
            $table->decimal('porcentaje_avance', 5, 2)->default(0);
            $table->string('estado', 50)->default('en_progreso');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign('fk_tema_tit')->references('id_tema_tit')->on('tema_titulacion')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE public.ficha_seguimiento ADD CONSTRAINT ficha_porcentaje_valido CHECK (porcentaje_avance >= 0 AND porcentaje_avance <= 100)');
        DB::statement("ALTER TABLE public.ficha_seguimiento ADD CONSTRAINT ficha_estado_no_vacio CHECK (btrim(estado) <> '')");
    }

    private function createProgressActivity(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
                CREATE TABLE actividad_avance (
                    id_actividad_av INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    fk_ficha INTEGER NOT NULL,
                    fk_docente INTEGER NOT NULL,
                    descripcion TEXT NOT NULL,
                    completada BOOLEAN NOT NULL DEFAULT 0,
                    fecha_registro DATE NOT NULL DEFAULT CURRENT_DATE,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT actividad_avance_descripcion_no_vacia CHECK (trim(descripcion) <> ''),
                    FOREIGN KEY (fk_ficha) REFERENCES ficha_seguimiento(id_ficha) ON DELETE RESTRICT,
                    FOREIGN KEY (fk_docente) REFERENCES usuario(id_usuario) ON DELETE RESTRICT
                )
                SQL);

            return;
        }

        Schema::create('actividad_avance', function (Blueprint $table): void {
            $table->increments('id_actividad_av');
            $table->unsignedInteger('fk_ficha');
            $table->unsignedInteger('fk_docente');
            $table->text('descripcion');
            $table->boolean('completada')->default(false);
            $table->date('fecha_registro')->default(DB::raw('CURRENT_DATE'));
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->foreign('fk_ficha')->references('id_ficha')->on('ficha_seguimiento')->restrictOnDelete();
            $table->foreign('fk_docente')->references('id_usuario')->on('usuario')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE public.actividad_avance ADD CONSTRAINT actividad_avance_descripcion_no_vacia CHECK (btrim(descripcion) <> '')");
    }

    public function down(): void
    {
        throw new RuntimeException('El seguimiento de titulación puede pertenecer al baseline y no admite rollback automático sin pérdida de información.');
    }
};
