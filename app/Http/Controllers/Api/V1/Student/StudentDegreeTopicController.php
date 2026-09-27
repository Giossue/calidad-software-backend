<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DegreeTopicResource;
use App\Models\TemaTitulacion;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class StudentDegreeTopicController extends Controller
{
    /**
     * Muestra las propuestas de tema de titulación del estudiante autenticado,
     * incluyendo el estado de revisión y las observaciones registradas por coordinación.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Usuario $user */
        $user = $request->user();

        Gate::authorize('viewOwn', TemaTitulacion::class);

        $topics = TemaTitulacion::query()
            ->with(TemaTitulacion::REVIEW_RELATIONS)
            ->where('fk_id_usuario', $user->getKey())
            ->orderByDesc('fecha_propuesta')
            ->orderByDesc('id_tema_tit')
            ->get();

        return DegreeTopicResource::collection($topics);
    }
}
