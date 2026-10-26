<?php

namespace App\Http\Requests\Api\V1\Teacher;

/** Vista previa del informe final PAO: consulta, sin escritura, para el docente asignado. */
class FinalPaoReportPreviewRequest extends TeacherListRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return FinalPaoReportRequest::reportRules(requireTutorings: false);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return FinalPaoReportRequest::reportAttributes();
    }
}
