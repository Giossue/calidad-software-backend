<?php

namespace App\Http\Requests\Api\V1\Student;

use App\Models\TemaTitulacion;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateStudentDegreeTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Usuario|null $user */
        $user = $this->user();
        /** @var TemaTitulacion|null $topic */
        $topic = $this->route('topic');

        if (! $user || (! $user->hasRole('estudiante') && ! $user->hasRole('administrador'))) {
            return false;
        }

        if ($topic instanceof TemaTitulacion && ! $user->hasRole('administrador')) {
            return (int) $topic->fk_id_usuario === (int) $user->getKey();
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['bail', 'required_without:titulo', 'nullable', 'string', 'min:5', 'max:255'],
            'titulo' => ['bail', 'required_without:title', 'nullable', 'string', 'min:5', 'max:255'],
            'description' => ['bail', 'nullable', 'string', 'max:2000'],
            'descripcion' => ['bail', 'nullable', 'string', 'max:2000'],
            'section_id' => ['bail', 'nullable', 'integer', 'exists:paralelo,id_paralelo'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'title' => 'título del tema',
            'titulo' => 'título del tema',
            'description' => 'descripción',
            'descripcion' => 'descripción',
            'section_id' => 'paralelo',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var TemaTitulacion|null $topic */
            $topic = $this->route('topic');

            if ($topic instanceof TemaTitulacion) {
                if ($topic->estado === 'aprobado') {
                    $validator->errors()->add(
                        'title',
                        'El tema de titulación ya fue aprobado previamente y no puede ser modificado.',
                    );
                } elseif ($topic->estado === 'rechazado') {
                    $validator->errors()->add(
                        'title',
                        'Este tema de titulación fue rechazado. Para presentar una alternativa, debes registrar una nueva propuesta.',
                    );
                } elseif ($topic->estado !== 'pendiente') {
                    $validator->errors()->add(
                        'title',
                        'Esta propuesta fue reemplazada por otra y ya no puede modificarse. Modifica tu propuesta vigente.',
                    );
                }
            }
        });
    }

    public function resolvedTitle(): string
    {
        return (string) ($this->input('title') ?? $this->input('titulo'));
    }

    public function resolvedDescription(): ?string
    {
        $desc = $this->input('description') ?? $this->input('descripcion');

        return $desc !== null ? (string) $desc : null;
    }
}
