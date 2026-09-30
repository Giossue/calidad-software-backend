<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Actions\Teacher\RegisterGrade;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Teacher\GradeRequest;
use App\Http\Resources\Api\V1\Teacher\EnrollmentResource;
use App\Models\AsignaturaTutoria;
use App\Models\InscripcionTutoria;

class GradeController extends Controller
{
    public function store(GradeRequest $request, AsignaturaTutoria $tutoring, InscripcionTutoria $enrollment, string $type, RegisterGrade $action): EnrollmentResource
    {
        return EnrollmentResource::make($action->execute($request->user(), $tutoring, $enrollment, $type, (string) $request->input('value')));
    }
}
