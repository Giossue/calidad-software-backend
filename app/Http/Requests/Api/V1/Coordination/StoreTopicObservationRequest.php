<?php

namespace App\Http\Requests\Api\V1\Coordination;

use Illuminate\Foundation\Http\FormRequest;

class StoreTopicObservationRequest extends FormRequest
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
            'observation' => ['bail', 'required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'observation' => 'observación',
        ];
    }
}
