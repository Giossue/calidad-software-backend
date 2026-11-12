<?php

namespace App\Imports\Importers;

use App\Http\Requests\Api\V1\Admin\StoreCareerRequest;

/**
 * Mismas reglas que el alta individual, salvo que la columna «codigo» es
 * opcional en la importación masiva de carreras.
 */
class CareerRowRequest extends StoreCareerRequest
{
    protected function codeIsRequired(): bool
    {
        return false;
    }
}
