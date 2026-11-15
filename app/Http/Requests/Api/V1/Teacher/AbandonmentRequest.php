<?php

namespace App\Http\Requests\Api\V1\Teacher;

/** Marca o quita el abandono de un estudiante; marcarlo exige el motivo. */
class AbandonmentRequest extends TeacherMutationRequest
{
    /** @return array<string, array<int, mixed>> */
    public static function fieldRules(): array
    {
        return [
            'abandoned' => ['required', 'boolean'],
            'reason' => ['exclude_unless:abandoned,true', 'required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public static function fieldAttributes(): array
    {
        return ['abandoned' => 'abandono', 'reason' => 'motivo'];
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return self::fieldRules();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return self::fieldAttributes();
    }
}
