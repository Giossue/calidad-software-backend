<?php

namespace App\Http\Requests\Api\V1\Tutoring;

use App\Models\Ciclo;
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

        return [
            'career_id' => $creating ? ['required', 'integer', Rule::exists('carrera', 'id_carrera')->where('estado', true)] : ['prohibited'],
            'code' => ['nullable', 'string', 'max:30',
                Rule::unique('subjects', 'code')
                    ->where('career_id', $careerId)
                    ->ignore($this->route('subject'))],
            // La asignatura es de un número de ciclo de la malla; los paralelos se eligen en cada tutoría del período.
            'cycle_number' => ['nullable', 'integer', 'min:1', 'max:'.$this->maxCycle($careerId)],
            'cycle_id' => $creating
                ? ['nullable', 'integer', Rule::exists('ciclo', 'id_ciclo')->where('estado', true)->where('fk_carrera', $careerId)]
                : ['prohibited'],
            'parallel_id' => ['prohibited'],
            'parallel_ids' => ['prohibited'],
            'new_parallel_name' => ['prohibited'],
            'period_id' => ['prohibited'],
            'modality_id' => ['nullable', 'integer', Rule::exists('modalidad', 'id_modalidad')->where('estado', true)],
            'next_subject_ids' => ['sometimes', 'nullable', 'array', 'max:10'],
            'next_subject_ids.*' => ['integer', 'distinct', Rule::exists('subjects', 'id')->where('career_id', $careerId),
                Rule::notIn($subject instanceof Subject ? [$subject->getKey()] : [])],
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

    /** Mayor número de ciclo activo de la carrera; 0 si aún no tiene ciclos. */
    private function maxCycle(?int $careerId): int
    {
        return (int) Ciclo::query()->where('fk_carrera', $careerId)->where('estado', true)->max('numero');
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $sections = 'Los paralelos se eligen al crear cada tutoría del período, no en la asignatura.';

        return [
            'cycle_number.max' => 'El ciclo debe estar entre 1 y :max, los ciclos de la carrera.',
            'cycle_number.min' => 'El ciclo debe ser 1 o mayor.',
            'parallel_id.prohibited' => $sections,
            'parallel_ids.prohibited' => $sections,
            'new_parallel_name.prohibited' => $sections,
            'period_id.prohibited' => 'La asignatura pertenece a la malla y no a un período; el período se elige en cada tutoría.',
            'next_subject_ids.*.exists' => 'La asignatura que continúa debe pertenecer a la misma carrera.',
            'next_subject_ids.*.not_in' => 'Una asignatura no puede continuar en sí misma.',
        ];
    }
}
