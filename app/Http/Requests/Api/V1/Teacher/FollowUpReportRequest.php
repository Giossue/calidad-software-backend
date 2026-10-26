<?php

namespace App\Http\Requests\Api\V1\Teacher;

class FollowUpReportRequest extends TeacherMutationRequest
{
    /** @return array<string, array<int, mixed>> */
    public static function reportRules(): array
    {
        return [
            'partial' => ['required', 'integer', 'in:1,2'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'observations' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public static function reportAttributes(): array
    {
        return ['partial' => 'parcial', 'from' => 'desde', 'to' => 'hasta', 'date' => 'fecha de presentación', 'observations' => 'observación'];
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return self::reportRules();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return self::reportAttributes();
    }
}
