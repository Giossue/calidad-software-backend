<?php

namespace App\Actions\AcademicPeriods;

use App\Models\AsignaturaTutoria;
use App\Models\PeriodoAcademico;
use Illuminate\Support\Facades\DB;

/**
 * Desactiva los períodos cuya fecha de finalización ya pasó, junto con sus
 * tutorías. Es la única vía por la que un PAO o una tutoría dejan de estar activos.
 */
class ExpireAcademicPeriods
{
    public function handle(): int
    {
        return DB::transaction(function (): int {
            $expiredIds = PeriodoAcademico::query()
                ->where('estado', true)
                ->whereDate('fecha_fin', '<', PeriodoAcademico::today())
                ->lockForUpdate()
                ->pluck('id_periodo');

            if ($expiredIds->isEmpty()) {
                return 0;
            }

            AsignaturaTutoria::query()->whereIn('fk_periodo', $expiredIds)->where('estado', true)->update(['estado' => false]);

            return PeriodoAcademico::query()->whereKey($expiredIds)->update(['estado' => false]);
        });
    }
}
