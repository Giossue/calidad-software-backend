<?php

namespace App\Http\Requests\Api\V1\Teacher;

use App\Support\TopicFiles;

class TopicFileRequest extends TeacherMutationRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.TopicFiles::MAX_KILOBYTES, 'extensions:'.implode(',', TopicFiles::EXTENSIONS), 'mimes:'.implode(',', TopicFiles::EXTENSIONS)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.max' => 'El archivo no puede superar los 20 MB.',
            'file.extensions' => 'Solo se permiten archivos PDF, Word, PowerPoint, Excel o de texto.',
            'file.mimes' => 'El contenido del archivo no corresponde a un PDF, Word, PowerPoint, Excel o texto.',
            'file.uploaded' => 'No fue posible subir el archivo. Verifica que no supere los 20 MB.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['file' => 'archivo'];
    }
}
