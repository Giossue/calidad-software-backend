<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Varias mallas por carrera. Una carrera tiene una o más mallas, exactamente una
 * vigente; cada asignatura pertenece a una malla y cada estudiante sigue una.
 *
 * Cada carrera existente recibe una malla vigente «Malla inicial» con todas sus
 * asignaturas y sus estudiantes. El código de asignatura pasa a ser único en la
 * malla (no en la carrera): dos mallas de la misma carrera pueden repetirlo.
 *
 * `down()` solo restaura el esquema anterior mientras cada carrera tenga una sola
 * malla; de lo contrario se detiene y nombra las carreras, para no perder datos.
 */
return new class extends Migration
{
    private const CURRENT_INDEX = 'malla_one_current_per_career';

    public function up(): void
    {
        Schema::create('malla', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('career_id');
            $table->string('name', 100);
            $table->boolean('is_current')->default(false);
            $table->timestampsTz();
            $table->foreign('career_id')->references('id_carrera')->on('carrera')->restrictOnDelete();
            $table->unique(['career_id', 'name']);
        });

        // Una sola malla vigente por carrera: lo garantiza el código y también la base.
        $condition = DB::getDriverName() === 'pgsql' ? 'is_current = true' : 'is_current = 1';
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS '.self::CURRENT_INDEX." ON malla (career_id) WHERE {$condition}");

        $now = now();
        foreach (DB::table('carrera')->orderBy('id_carrera')->pluck('id_carrera') as $careerId) {
            DB::table('malla')->insert([
                'career_id' => $careerId, 'name' => 'Malla inicial', 'is_current' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        Schema::table('subjects', function (Blueprint $table): void {
            $table->unsignedInteger('malla_id')->nullable()->after('career_id');
            $table->foreign('malla_id')->references('id')->on('malla')->restrictOnDelete();
        });
        DB::table('subjects')->update([
            'malla_id' => DB::table('malla')->whereColumn('malla.career_id', 'subjects.career_id')->select('id'),
        ]);
        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropUnique(['career_id', 'code']);
        });
        Schema::table('subjects', function (Blueprint $table): void {
            $table->unsignedInteger('malla_id')->nullable(false)->change();
            $table->unique(['malla_id', 'code']);
        });

        Schema::table('usuario', function (Blueprint $table): void {
            $table->unsignedInteger('malla_id')->nullable();
            $table->foreign('malla_id')->references('id')->on('malla')->restrictOnDelete();
        });
        DB::table('usuario')
            ->whereNotNull('fk_carrera')
            ->whereIn('id_usuario', DB::table('role_user')
                ->join('roles', 'roles.id', '=', 'role_user.role_id')
                ->where('roles.slug', 'estudiante')
                ->select('role_user.user_id'))
            ->update([
                'malla_id' => DB::table('malla')->whereColumn('malla.career_id', 'usuario.fk_carrera')->select('id'),
            ]);
    }

    public function down(): void
    {
        $careers = DB::table('carrera')
            ->whereIn('id_carrera', DB::table('malla')->groupBy('career_id')->havingRaw('count(*) > 1')->select('career_id'))
            ->orderBy('nombre')
            ->pluck('nombre');
        if ($careers->isNotEmpty()) {
            throw new RuntimeException('No se puede volver a una malla por carrera: estas carreras tienen varias mallas: '
                .$careers->implode(', ').'. Deja una sola malla en cada carrera antes de revertir.');
        }

        Schema::table('usuario', function (Blueprint $table): void {
            $table->dropForeign(['malla_id']);
        });
        Schema::table('usuario', function (Blueprint $table): void {
            $table->dropColumn('malla_id');
        });

        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropUnique(['malla_id', 'code']);
            $table->dropForeign(['malla_id']);
        });
        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropColumn('malla_id');
            $table->unique(['career_id', 'code']);
        });

        DB::statement('DROP INDEX IF EXISTS '.self::CURRENT_INDEX);
        Schema::dropIfExists('malla');
    }
};
