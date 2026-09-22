<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\FacultyResource;
use App\Models\Facultad;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class FacultyController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Facultad::class);

        return FacultyResource::collection(
            Facultad::query()->where('estado', true)->orderBy('nombre')->get(),
        );
    }
}
