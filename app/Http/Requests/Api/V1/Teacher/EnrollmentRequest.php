<?php

namespace App\Http\Requests\Api\V1\Teacher;

use App\Rules\CedulaOPasaporte;
use Illuminate\Validation\Rule;

class EnrollmentRequest extends TeacherMutationRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['name', 'email', 'phone'] as $field) {
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
                'name' => ['required', 'string', 'max:150', 'regex:/^[\pL\s]+$/u'],
                'phone' => ['nullable', 'digits:10'],
                'student_id' => ['prohibited'], 'identification' => ['prohibited'], 'email' => ['prohibited'],
            ];
        }
        $existing = Rule::prohibitedIf($this->filled('student_id'));

        return [
            'student_id' => ['nullable', 'integer', Rule::exists('usuario', 'id_usuario')->where('estado', true)],
            'identification' => [$existing, 'required_without:student_id', 'string', new CedulaOPasaporte, Rule::unique('usuario', 'cedula')],
            'name' => [$existing, 'required_without:student_id', 'string', 'max:150', 'regex:/^[\pL\s]+$/u'],
            'email' => [$existing, 'required_without:student_id', 'email:rfc', 'max:150', 'ends_with:@ueb.edu.ec', Rule::unique('usuario', 'correo')],
            'phone' => [$existing, 'nullable', 'digits:10'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['student_id' => 'estudiante', 'identification' => 'cédula o pasaporte', 'name' => 'nombre', 'email' => 'correo institucional', 'phone' => 'teléfono'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.digits' => 'El teléfono debe tener exactamente 10 dígitos numéricos.',
            'email.ends_with' => 'El correo institucional debe pertenecer al dominio @ueb.edu.ec.',
            'name.regex' => 'El nombre solo puede contener letras y espacios.',
        ];
    }
}
