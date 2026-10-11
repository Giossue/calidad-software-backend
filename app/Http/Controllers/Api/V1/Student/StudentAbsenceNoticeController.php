<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\AbsenceNotice;
use App\Models\AsignaturaTutoria;
use App\Models\Asistencia;
use App\Models\InscripcionTutoria;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * Avisos de inasistencia del estudiante: los envía antes de la clase y el
 * docente los ve al tomar lista (decide si queda presente o ausente).
 */
class StudentAbsenceNoticeController extends Controller
{
    private const DAYS = [0 => 'domingo', 1 => 'lunes', 2 => 'martes', 3 => 'miercoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sabado'];

    public function index(Request $request, AsignaturaTutoria $tutoring): JsonResponse
    {
        $enrollment = $this->enrollment($request, $tutoring);
        $notices = AbsenceNotice::query()->where('enrollment_id', $enrollment->getKey())->orderByDesc('date')->get();
        $attendance = Asistencia::query()->where('fk_inscripcion', $enrollment->getKey())->get()
            ->keyBy(fn (Asistencia $record) => $record->fecha->toDateString());

        return response()->json(['data' => $notices->map(fn (AbsenceNotice $notice) => self::present($notice, $attendance->get($notice->date->toDateString())))->all()]);
    }

    public function store(Request $request, AsignaturaTutoria $tutoring): JsonResponse
    {
        $enrollment = $this->enrollment($request, $tutoring);
        $periodEnd = $tutoring->periodo?->fecha_fin?->toDateString();

        $data = validator($request->all(), [
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', ...($periodEnd ? ['before_or_equal:'.$periodEnd] : [])],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'date.after_or_equal' => 'Solo puedes avisar inasistencias de hoy en adelante.',
            'date.before_or_equal' => 'La fecha debe estar dentro del período académico.',
        ], ['date' => 'fecha', 'reason' => 'motivo'])->after(function (Validator $validator) use ($request, $tutoring, $enrollment): void {
            $date = (string) $request->input('date');
            if (! $validator->errors()->has('date') && AbsenceNotice::query()->where('enrollment_id', $enrollment->getKey())->whereDate('date', $date)->exists()) {
                $validator->errors()->add('date', 'Ya enviaste un aviso de inasistencia para esa fecha.');
            }
            $days = $tutoring->horarios()->where('estado', true)->pluck('dia_semana')->all();
            if ($days !== [] && ! $validator->errors()->has('date') && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
                && ! in_array(self::DAYS[Carbon::parse($date)->dayOfWeek], $days, true)) {
                $validator->errors()->add('date', 'Esa fecha no tiene clase según el horario de la tutoría.');
            }
        })->validate();

        $notice = AbsenceNotice::query()->create([
            'enrollment_id' => $enrollment->getKey(),
            'date' => $data['date'],
            'reason' => trim($data['reason']),
        ]);

        return response()->json(['data' => self::present($notice, null)], Response::HTTP_CREATED);
    }

    /** @return array<string, mixed> */
    private static function present(AbsenceNotice $notice, ?Asistencia $attendance): array
    {
        return [
            'id' => $notice->getKey(),
            'date' => $notice->date->toDateString(),
            'reason' => $notice->reason,
            'created_at' => $notice->created_at?->toIso8601String(),
            // pendiente: aún no se toma lista de esa fecha; luego, lo que registró el docente.
            'status' => $attendance === null ? 'pendiente' : ($attendance->estado_asistencia ? 'presente' : 'ausente'),
        ];
    }

    private function enrollment(Request $request, AsignaturaTutoria $tutoring): InscripcionTutoria
    {
        /** @var Usuario $user */
        $user = $request->user();
        abort_unless($user->hasRole('estudiante'), Response::HTTP_FORBIDDEN, 'Solo los estudiantes pueden avisar inasistencias.');

        return InscripcionTutoria::query()->where('fk_id_usuario', $user->getKey())
            ->where('fk_asig_tutoria', $tutoring->getKey())->where('estado', true)->first()
            ?? abort(Response::HTTP_FORBIDDEN, 'No estás inscrito en esta tutoría.');
    }
}
