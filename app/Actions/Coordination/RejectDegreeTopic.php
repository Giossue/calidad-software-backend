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
        return DB::transaction(function () use ($topic, $coordinator, $observation): TemaTitulacion {
            $topic = TemaTitulacion::query()->whereKey($topic->getKey())->lockForUpdate()->firstOrFail();

            if ($topic->estado !== 'pendiente') {
                throw ValidationException::withMessages([
                    'topic' => ['Solo se pueden rechazar propuestas pendientes de revisión.'],
                ]);
            }

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

            $topic->asignaciones()->where('estado', true)->update(['estado' => false]);

            return $topic->load(TemaTitulacion::REVIEW_RELATIONS);
        });
    }
}
