<?php

namespace App\Http\Requests\Api\V1\Tutoring;

use App\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subject = $this->route('subject');

        return $subject instanceof Subject
            ? $this->user()->can('update', $subject)
            : $this->user()->can('create', Subject::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $subject = $this->route('subject');
        $careerId = $subject instanceof Subject ? $subject->career_id : $this->integer('career_id');

        return [
            'career_id' => $creating ? ['required', 'integer', Rule::exists('carrera', 'id_carrera')->where('estado', true)] : ['prohibited'],
            'code' => [($creating ? 'required' : 'sometimes'), 'string', 'max:30',
                Rule::unique('subjects', 'code')
                    ->where('career_id', $careerId)
                    ->ignore($this->route('subject'))],
            'name' => [($creating ? 'required' : 'sometimes'), 'string', 'max:150'],
        ];
    }
}
