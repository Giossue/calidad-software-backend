<?php

namespace App\Http\Middleware;

use App\Actions\AcademicPeriods\ExpireAcademicPeriods as ExpireAcademicPeriodsAction;
use App\Models\PeriodoAcademico;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aplica la expiración de los PAO antes de atender la solicitud. El contenedor
 * no ejecuta un scheduler, así que la comprobación se hace aquí una vez por día.
 */
class ExpireAcademicPeriods
{
    public function __construct(private readonly ExpireAcademicPeriodsAction $expireAcademicPeriods) {}

    public function handle(Request $request, Closure $next): Response
    {
        $cacheKey = 'academic-periods:expired:'.PeriodoAcademico::today();

        if (! Cache::has($cacheKey)) {
            $this->expireAcademicPeriods->handle();
            Cache::put($cacheKey, true, now()->addDay());
        }

        return $next($request);
    }
}
