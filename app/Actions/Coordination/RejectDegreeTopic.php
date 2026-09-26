<?php

namespace App\Actions\Coordination;

use App\Models\ObservacionTitulacion;
use App\Models\TemaTitulacion;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejectDegreeTopic
{
    public function handle(TemaTitulacion $topic, Usuario $coordinator, ?string $observation = null): TemaTitulacion
    {
        if ($topic->estado === 'aprobado') {
            throw ValidationException::withMessages([
                'topic' => ['El tema de titulación ya fue aprobado previamente y no puede ser rechazado.'],
            ]);
        }

        if ($topic->estado === 'rechazado') {
            throw ValidationException::withMessages([
                'topic' => ['El tema de titulación ya fue rechazado previamente.'],
            ]);
        }

        return DB::transaction(function () use ($topic, $coordinator, $observation): TemaTitulacion {
            $topic->update([
                'estado' => 'rechazado',
                'fecha_revision' => now()->toDateString(),
                'fk_coord_revisor' => $coordinator->getKey(),
            ]);

            if ($observation !== null && trim($observation) !== '') {
                ObservacionTitulacion::query()->create([
                    'fk_tema_tit' => $topic->getKey(),
                    'fk_coord_tit' => $coordinator->getKey(),
                    'descripcion' => trim($observation),
                    'fecha_registro' => now()->toDateString(),
                ]);
            }

            /** @var TemaTitulacion */
            return $topic->fresh([
                'estudiante.paralelos',
                'periodo',
                'coordinadorRevisor',
                'asignaciones.docente',
                'observaciones.coordinador',
            ]);
        });
    }
}
