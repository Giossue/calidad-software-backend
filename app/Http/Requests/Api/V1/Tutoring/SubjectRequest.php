<?php

namespace App\Http\Requests\Api\V1\Tutoring;

use App\Models\Carrera;
use App\Models\Subject;
use App\Support\Curricula;
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
     * Normalize a subject name: strip accents and convert to uppercase.
     * This prevents duplicates like "Matemáticas" vs "matematicas" vs "MATEMATICAS".
     */
    public static function normalizeName(string $name): string
    {
        $name = trim($name);
        // Decompose characters so accents become separate code points
        $normalized = Normalizer::normalize($name, Normalizer::FORM_D);
        // Remove non-spacing marks (accents)
        $normalized = preg_replace('/\p{Mn}/u', '', $normalized ?? $name);

        return mb_strtoupper($normalized ?? $name);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $subject = $this->route('subject');
        $careerId = $subject instanceof Subject ? $subject->career_id : $this->integer('career_id');
        // Código y nombre son únicos dentro de cada versión de la malla.
        $curriculumId = $subject instanceof Subject ? $subject->curriculum_id : ($this->integer('curriculum_id') ?: $this->curriculumInForce($careerId));

        return [
            'career_id' => $creating ? ['required', 'integer', Rule::exists('carrera', 'id_carrera')->where('estado', true)] : ['prohibited'],
            'curriculum_id' => $creating ? ['nullable', 'integer', Rule::exists('curricula', 'id')->where('career_id', $careerId)] : ['prohibited'],
            'code' => ['nullable', 'string', 'max:30',
                Rule::unique('subjects', 'code')
                    ->where('curriculum_id', $curriculumId)
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
            'next_subject_ids' => ['sometimes', 'nullable', 'array', 'max:10'],
            'next_subject_ids.*' => ['integer', 'distinct', Rule::exists('subjects', 'id')->where('career_id', $careerId)->where('curriculum_id', $curriculumId),
                Rule::notIn($subject instanceof Subject ? [$subject->getKey()] : [])],
            'name' => [
                ($creating ? 'required' : 'sometimes'),
                'string',
                'max:150',
                Rule::unique('subjects', 'name')
                    ->where('curriculum_id', $curriculumId)
                    ->ignore($this->route('subject')),
            ],
        ];
    }

    private function curriculumInForce(int $careerId): ?int
    {
        return $careerId > 0 && Carrera::query()->whereKey($careerId)->exists() ? Curricula::inForceAt($careerId)->id : null;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'next_subject_ids.*.exists' => 'La asignatura que continúa debe pertenecer a la misma carrera.',
            'next_subject_ids.*.not_in' => 'Una asignatura no puede continuar en sí misma.',
        ];
    }
}
