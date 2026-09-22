<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreFacultyRequest;
use App\Http\Requests\Api\V1\Admin\UpdateFacultyRequest;
use App\Http\Resources\Api\V1\FacultyResource;
use App\Models\Facultad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class FacultyController extends Controller
{
    public function store(StoreFacultyRequest $request): JsonResponse
    {
        $faculty = Facultad::create([
            'nombre' => $request->validated('name'),
        ])->refresh();

        return response()->json([
            'data' => new FacultyResource($faculty),
        ], Response::HTTP_CREATED);
    }

    public function update(UpdateFacultyRequest $request, Facultad $faculty): JsonResponse
    {
        if ($request->exists('name')) {
            $faculty->nombre = $request->validated('name');
            $faculty->save();
        }

        return response()->json([
            'data' => new FacultyResource($faculty),
        ]);
    }

    public function deactivate(Facultad $faculty): JsonResponse
    {
        $faculty->estado = false;
        $faculty->save();

        return response()->json([
            'data' => new FacultyResource($faculty),
        ]);
    }
}
