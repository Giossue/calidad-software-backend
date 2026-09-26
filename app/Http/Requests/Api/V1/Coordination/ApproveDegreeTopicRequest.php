<?php

namespace App\Http\Requests\Api\V1\Coordination;

use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ApproveDegreeTopicRequest extends FormRequest
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
            'tutor_id' => ['bail', 'required', 'integer', 'exists:usuario,id_usuario'],
            'peer_ids' => ['bail', 'required', 'array', 'min:1'],
            'peer_ids.*' => [
                'bail',
                'required',
                'integer',
                'distinct',
                'exists:usuario,id_usuario',
                'different:tutor_id',
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'tutor_id' => 'docente tutor',
            'peer_ids' => 'pares académicos',
            'peer_ids.*' => 'par académico',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'peer_ids.*.different' => 'Un docente no puede ser asignado simultáneamente como tutor y como par académico.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $tutorId = $this->input('tutor_id');
            if ($tutorId) {
                /** @var Usuario|null $tutor */
                $tutor = Usuario::query()->find($tutorId);
                if (! $tutor || ! $tutor->hasRole('docente') || ! $tutor->estado) {
                    $validator->errors()->add('tutor_id', 'El tutor seleccionado debe ser un docente activo.');
                }
            }

            $peerIds = (array) $this->input('peer_ids', []);
            foreach ($peerIds as $index => $peerId) {
                /** @var Usuario|null $peer */
                $peer = Usuario::query()->find($peerId);
                if (! $peer || ! $peer->hasRole('docente') || ! $peer->estado) {
                    $validator->errors()->add("peer_ids.{$index}", 'El par académico seleccionado debe ser un docente activo.');
                }
            }
        });
    }
}
