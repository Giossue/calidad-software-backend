<?php

use App\Support\Uppercase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pasa a MAYÚSCULAS los nombres, catálogos, temas y títulos ya guardados; desde
 * ahora los modelos los guardan así (StoresUppercase).
 */
return new class extends Migration
{
    /** @var array<string, array{0: string, 1: string}> tabla => [clave, columna] */
    private const COLUMNS = [
        'usuario' => ['id_usuario', 'nombre'],
        'facultad' => ['id_facultad', 'nombre'],
        'carrera' => ['id_carrera', 'nombre'],
        'malla' => ['id', 'name'],
        'subjects' => ['id', 'name'],
        'tema' => ['id_tema', 'nombre'],
        'tema_titulacion' => ['id_tema_tit', 'titulo'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => [$key, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }
            DB::table($table)->select([$key, $column])->whereNotNull($column)->orderBy($key)
                ->chunkById(500, function ($rows) use ($table, $key, $column): void {
                    foreach ($rows as $row) {
                        $upper = Uppercase::text($row->{$column});
                        if ($upper !== $row->{$column}) {
                            DB::table($table)->where($key, $row->{$key})->update([$column => $upper]);
                        }
                    }
                }, $key);
        }
    }

    /** No se puede saber cómo estaba escrito cada texto: no se revierte. */
    public function down(): void {}
};
