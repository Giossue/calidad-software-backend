<?php

namespace App\Http\Requests\Api\V1\Tutoring;

use App\Models\Usuario;
use App\Rules\CedulaOPasaporte;
use App\Support\InstitutionalEmail;
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
            'identification' => [($creating ? 'required' : 'sometimes'), 'string',
                ...$this->lockedOnceVerified($teacher, 'cedula', 'cédula'), new CedulaOPasaporte,
                Rule::unique('usuario', 'cedula')->ignore($teacher)],
            'name' => [($creating ? 'required' : 'sometimes'), 'string', 'max:150', 'regex:/^[\pL\s]+$/u'],
            'email' => [($creating ? 'required' : 'sometimes'), ...$this->lockedOnceVerified($teacher, 'correo', 'correo'),
                'email:rfc', 'max:150', InstitutionalEmail::rule(),
                Rule::unique('usuario', 'correo')->ignore($teacher)],
            'phone' => [($creating ? 'required' : 'sometimes'), 'digits:10'],
        ];
    }

    /**
     * Cuando el docente ya completó su perfil (confirmó sus datos al ingresar), el coordinador
     * no puede cambiarle la cédula ni el correo; reenviar el mismo valor sí se acepta.
     *
     * @return array<int, \Closure>
     */
    private function lockedOnceVerified(mixed $teacher, string $column, string $label): array
    {
        if (! $teacher instanceof Usuario || $teacher->must_complete_profile) {
            return [];
        }

        return [function (string $attribute, mixed $value, \Closure $fail) use ($teacher, $column, $label): void {
            if (is_string($value) && mb_strtolower(trim($value)) !== mb_strtolower((string) $teacher->$column)) {
                $fail("El docente ya completó su perfil: su {$label} no se puede cambiar.");
            }
        }];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'identification' => 'cédula o pasaporte',
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
            'email.ends_with' => InstitutionalEmail::message(),
            'name.regex' => 'El nombre completo solo puede contener letras y espacios.',
        ];
    }
}
