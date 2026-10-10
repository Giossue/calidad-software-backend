<?php

use App\Http\Requests\Api\V1\Tutoring\SubjectRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Los nombres de asignatura pasan a guardarse en mayúsculas y sin tildes,
 * para que "Cálculo I", "calculo i" y "CALCULO I" sean la misma asignatura.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('subjects')->select(['id', 'name'])->orderBy('id')->each(function (object $subject): void {
            $name = SubjectRequest::normalizeName($subject->name);

            if ($name !== $subject->name) {
                DB::table('subjects')->where('id', $subject->id)->update(['name' => $name]);
            }
        });
    }

    public function down(): void
    {
        // Los nombres originales no se pueden recuperar.
    }
};
