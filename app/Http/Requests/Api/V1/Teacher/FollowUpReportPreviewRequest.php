<?php

namespace App\Http\Requests\Api\V1\Teacher;

/** Vista previa del reporte de seguimiento: consulta, sin escritura, para el docente asignado. */
class FollowUpReportPreviewRequest extends TeacherListRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return FollowUpReportRequest::reportRules();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return FollowUpReportRequest::reportAttributes();
    }
}
