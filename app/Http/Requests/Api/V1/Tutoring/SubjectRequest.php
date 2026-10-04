<?php

namespace App\Http\Requests\Api\V1\Tutoring;

use App\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Normalizer;

class SubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subject = $this->route('subject');

        return $subject instanceof Subject
            ? $this->user()->can('update', $subject)
            : $this->user()->can('create', Subject::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $code = trim((string) $this->input('code'));
            $this->merge(['code' => $code !== '' ? $code : null]);
        }

        if ($this->has('name')) {
            $this->merge(['name' => self::normalizeName((string) $this->input('name'))]);
        }
    }

    /**
     * Normalize a subject name: strip accents and convert to lowercase.
     * This prevents duplicates like "Matemáticas" vs "matematicas" vs "MATEMATICAS".
     */
    public static function normalizeName(string $name): string
    {
        $name = trim($name);
        // Decompose characters so accents become separate code points
        $normalized = Normalizer::normalize($name, Normalizer::FORM_D);
        // Remove non-spacing marks (accents)
        $normalized = preg_replace('/\p{Mn}/u', '', $normalized ?? $name);
        return mb_strtolower($normalized ?? $name);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $subject = $this->route('subject');
        $careerId = $subject instanceof Subject ? $subject->career_id : $this->integer('career_id');

        return [
            'career_id' => $creating ? ['required', 'integer', Rule::exists('carrera', 'id_carrera')->where('estado', true)] : ['prohibited'],
            'code' => ['nullable', 'string', 'max:30',
                Rule::unique('subjects', 'code')
                    ->where('career_id', $careerId)
                    ->ignore($this->route('subject'))],
            'cycle_id' => $creating
                ? ['nullable', 'integer', Rule::exists('ciclo', 'id_ciclo')->where('estado', true)->where('fk_carrera', $careerId)]
                : ['prohibited'],
            'parallel_id' => $creating
                ? ['nullable', 'integer', Rule::exists('paralelo', 'id_paralelo')->where('estado', true)]
                : ['prohibited'],
            'parallel_ids' => $creating
                ? ['nullable', 'array']
                : ['prohibited'],
            'parallel_ids.*' => ['integer', Rule::exists('paralelo', 'id_paralelo')->where('estado', true)],
            'new_parallel_name' => $creating
                ? ['nullable', 'string', 'max:50']
                : ['prohibited'],
            'modality_id' => ['nullable', 'integer', Rule::exists('modalidad', 'id_modalidad')->where('estado', true)],
            'period_id' => $creating
                ? ['nullable', 'integer', Rule::exists('periodo_academico', 'id_periodo')->where('estado', true)]
                : ['prohibited'],
            'name' => [
                ($creating ? 'required' : 'sometimes'),
                'string',
                'max:150',
                Rule::unique('subjects', 'name')
                    ->where('career_id', $careerId)
                    ->ignore($this->route('subject')),
            ],
        ];
    }
}
