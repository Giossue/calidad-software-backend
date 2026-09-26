<?php

namespace App\Http\Requests\Api\V1\Coordination;

use App\Models\TemaTitulacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RejectDegreeTopicRequest extends FormRequest
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
            'observation' => ['bail', 'nullable', 'string', 'max:1000'],
            'reason' => ['bail', 'nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'observation' => 'observación',
            'reason' => 'motivo de rechazo',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $topic = $this->route('topic');

            if ($topic instanceof TemaTitulacion) {
                if ($topic->estado === 'aprobado') {
                    $validator->errors()->add('topic', 'El tema de titulación ya fue aprobado previamente y no puede ser rechazado.');
                } elseif ($topic->estado === 'rechazado') {
                    $validator->errors()->add('topic', 'El tema de titulación ya fue rechazado previamente.');
                }
            }
        });
    }
}
