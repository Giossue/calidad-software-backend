<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roles conocidos hasta ahora. Mantenidos en el mismo orden que el CHECK
     * que reemplazan (0000_01_01_000000_create_domain_user_table.php).
     *
     * @var array<string, string>
     */
    private const ROLES = [
        'estudiante' => 'Estudiante',
        'docente' => 'Docente',
        'coordinador_carrera' => 'Coordinador de carrera',
        'coordinador_titulacion' => 'Coordinador de titulación',
        'administrador' => 'Administrador',
    ];

    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name', 100);
            $table->timestampsTz();
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->unsignedInteger('user_id');
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestampTz('assigned_at')->useCurrent();

            $table->foreign('user_id')->references('id_usuario')->on('usuario')->cascadeOnDelete();
            $table->primary(['user_id', 'role_id']);
        });

        $now = now();
        DB::table('roles')->insert(
            collect(self::ROLES)->map(fn (string $name, string $slug): array => [
                'slug' => $slug,
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ])->values()->all(),
        );

        if (Schema::hasColumn('usuario', 'rol')) {
            $roleIds = DB::table('roles')->pluck('id', 'slug');

            DB::table('usuario')->select('id_usuario', 'rol')->orderBy('id_usuario')
                ->each(function (object $user) use ($roleIds, $now): void {
                    if (! isset($roleIds[$user->rol])) {
                        return;
                    }

                    DB::table('role_user')->insert([
                        'user_id' => $user->id_usuario,
                        'role_id' => $roleIds[$user->rol],
                        'assigned_at' => $now,
                    ]);
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
    }
};
