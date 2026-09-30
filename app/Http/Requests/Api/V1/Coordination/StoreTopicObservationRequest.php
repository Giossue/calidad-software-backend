<?php

namespace App\Http\Requests\Api\V1\Coordination;

use App\Models\TemaTitulacion;
use Illuminate\Foundation\Http\FormRequest;

class StoreTopicObservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', TemaTitulacion::class) ?? false;
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
