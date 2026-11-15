<?php

namespace App\Http\Requests\Api\V1\Teacher;

use App\Http\Requests\Concerns\CombinesPersonNames;
use App\Rules\CedulaOPasaporte;
use App\Support\InstitutionalEmail;
use Illuminate\Validation\Rule;

class EnrollmentRequest extends TeacherMutationRequest
{
    use CombinesPersonNames;

    protected function prepareForValidation(): void
    {
        $this->preparePersonNames();
        foreach (['email', 'phone'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $this->merge([$field => $field === 'email' ? strtolower($value) : $value]);
            }
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        if ($this->isMethod('patch')) {
            return [
                ...$this->personNameRules(false),
                'phone' => ['nullable', 'digits:10'],
                'student_id' => ['prohibited'], 'identification' => ['prohibited'], 'email' => ['prohibited'],
            ];
        }
        $existing = Rule::prohibitedIf($this->filled('student_id'));
        // Los nombres y apellidos solo se piden al registrar un estudiante nuevo.
        $names = array_map(
            fn (array $rules): array => [$existing, 'required_without:student_id', ...array_filter($rules, fn (mixed $rule): bool => $rule !== 'required')],
            $this->personNameRules(),
        );

        return [
            'student_id' => ['nullable', 'integer', Rule::exists('usuario', 'id_usuario')->where('estado', true)],
            'identification' => [$existing, 'required_without:student_id', 'string', new CedulaOPasaporte, Rule::unique('usuario', 'cedula')],
            ...$names,
            'email' => [$existing, 'required_without:student_id', 'email:rfc', 'max:150', InstitutionalEmail::rule(), Rule::unique('usuario', 'correo')],
            'phone' => [$existing, 'nullable', 'digits:10'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['student_id' => 'estudiante', 'identification' => 'cédula o pasaporte', ...$this->personNameAttributes(), 'email' => 'correo institucional', 'phone' => 'teléfono'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.digits' => 'El teléfono debe tener exactamente 10 dígitos numéricos.',
            'email.ends_with' => InstitutionalEmail::message(),
            ...$this->personNameMessages(),
        ];
    }
}
