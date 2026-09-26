<?php

namespace App\Http\Requests\Api\V1\Coordination;

use App\Models\TemaTitulacion;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAcademicPeersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->hasRole('coordinador_titulacion') || $user->hasRole('administrador'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'peer_ids' => ['bail', 'required', 'array', 'min:1'],
            'peer_ids.*' => [
                'bail',
                'required',
                'integer',
                'distinct',
                'exists:usuario,id_usuario',
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'peer_ids' => 'pares académicos',
            'peer_ids.*' => 'par académico',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $topic = $this->route('topic');
            $tutorId = null;

            if ($topic instanceof TemaTitulacion) {
                $tutorId = $topic->asignaciones()
                    ->where('rol', 'tutor')
                    ->where('estado', true)
                    ->value('fk_id_usuario');
            }

            $peerIds = (array) $this->input('peer_ids', []);
            foreach ($peerIds as $index => $peerId) {
                /** @var Usuario|null $peer */
                $peer = Usuario::query()->find($peerId);
                if (! $peer || ! $peer->hasRole('docente') || ! $peer->estado) {
                    $validator->errors()->add("peer_ids.{$index}", 'El par académico seleccionado debe ser un docente activo.');
                }

                if ($tutorId !== null && (int) $peerId === (int) $tutorId) {
                    $validator->errors()->add("peer_ids.{$index}", 'El docente asignado como tutor no puede ser asignado simultáneamente como par académico.');
                }
            }
        });
    }
}
