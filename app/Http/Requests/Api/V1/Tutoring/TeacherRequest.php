<?php

namespace App\Http\Requests\Api\V1\Tutoring;

use App\Models\Usuario;
use App\Rules\CedulaEcuatoriana;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeacherRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function authorize(): bool
    {
        $teacher = $this->route('teacher');

        return $teacher instanceof Usuario
            ? $this->user()->can('updateTutoringTeacher', $teacher)
            : $this->user()->can('createTutoringTeacher', Usuario::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $teacher = $this->route('teacher');

        return [
            'career_id' => $creating ? ['required', 'integer', Rule::exists('carrera', 'id_carrera')->where('estado', true)] : ['prohibited'],
            'identification' => [($creating ? 'required' : 'sometimes'), 'digits:10', new CedulaEcuatoriana,
                Rule::unique('usuario', 'cedula')->ignore($teacher)],
            'name' => [($creating ? 'required' : 'sometimes'), 'string', 'max:150', 'regex:/^[\pL\s]+$/u'],
            'email' => [($creating ? 'required' : 'sometimes'), 'email:rfc', 'max:150', 'ends_with:@ueb.edu.ec',
                Rule::unique('usuario', 'correo')->ignore($teacher)],
            'phone' => [($creating ? 'required' : 'sometimes'), 'digits:10'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'identification' => 'cédula',
            'name' => 'nombre completo',
            'email' => 'correo institucional',
            'phone' => 'teléfono',
            'career_id' => 'carrera',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.digits' => 'El teléfono debe tener exactamente 10 dígitos numéricos.',
            'identification.digits' => 'La cédula debe tener exactamente 10 dígitos numéricos.',
            'email.ends_with' => 'El correo institucional debe pertenecer al dominio @ueb.edu.ec.',
            'name.regex' => 'El nombre completo solo puede contener letras y espacios.',
        ];
    }
}
