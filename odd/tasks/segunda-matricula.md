# Segunda matrícula: asistencia obligatoria a tutorías

Objective: record the matrícula number (1.ª/2.ª/3.ª) of each student's enrollment in a tutoría; only students in 2.ª matrícula or higher must attend. Repos: calidad-software-backend + calidad-software-frontend, branch `main` (team pushes to main). Delivery: work-unit commits per repo, push on user request. Runners: `env -u DB_CONNECTION -u DB_HOST -u DB_PORT -u DB_DATABASE -u DB_USERNAME -u DB_PASSWORD -u DB_URL php artisan test` (SQLite), `npx vitest run`, `npx tsc --noEmit -p tsconfig.app.json`, `npm run lint`.

## Specs

- **S1 — Solo 2.ª matrícula es obligatoria (L1, L2).** "Solo los estudiantes que cuenten con segunda matrícula deberían tener que asistir a tutorías de forma obligatoria". An enrollment with matrícula number ≥ 2 has mandatory attendance; 1.ª matrícula does not. It is shown as a label, "Asistencia obligatoria", next to the student in the tutoría student lists (teacher Estudiantes, teacher attendance, coordinator tutoría Estudiantes) together with the matrícula ("2.ª matrícula"). Nothing blocks or changes attendance taking.
- **S2 — Se registra al inscribir (L2).** Answer: "Se registra al inscribir (Recomendado)": a field "Matrícula: 1.ª / 2.ª / 3.ª" when enrolling the student in the tutoría (coordinator or teacher, existing or new student; default 1.ª), editable later in the enrollment edit form. The roster import reads the academic system's LISTADO PDF, which has no matrícula: imported students start in 1.ª and are adjusted by editing the enrollment (the import logic already accepts a per-row `matricula` value for a future reader). Students enrolled automatically (advancement to the next subject) start in 1.ª. Existing enrollments become 1.ª.
- **S3 — Plan de acción del diagnóstico (L2).** Answer about the diagnostic action plan ("Tutorías adicionales obligatorias" for groups Bajo and Medio): "Acá debemos cambiar, creo que eso sería más bien dependiendo lo que consideren los docentes y coordinadores". Assumption: the action-plan item becomes "Tutorías adicionales según el criterio del docente y del coordinador" (diagnostic group no longer makes tutorías mandatory). At-risk statistics in reports stay as they are.

Out of scope: report statistics by matrícula; computing matrícula from history.

## API contract (backend ↔ frontend)

- Storage: `inscripcion_tutoria.numero_matricula` smallint, not null, default 1, values 1–3.
- Enrollment payloads (teacher `EnrollmentResource`, coordinator tutoría students, coordinator student enrollments, available lists where an enrollment is returned) add `enrollment_number: 1|2|3` and `attendance_required: boolean` (`enrollment_number >= 2`). Teacher session attendance rows (`SessionResource.attendance[]`) add `attendance_required`.
- Teacher `POST tutorings/{t}/students` and `PATCH tutorings/{t}/students/{e}`; coordinator `POST tutoring-coordination/tutorings/{t}/students`, `PATCH .../tutorings/{t}/students/{e}` and `POST .../students/{student}/enroll`: accept optional `enrollment_number` (integer 1–3; on POST default 1; on PATCH absent = unchanged). Message: "La matrícula debe ser 1.ª, 2.ª o 3.ª." Attribute: "matrícula".
- Roster import (logic only; the PDF reader `RosterPdf` does not supply it yet): per-row `matricula` (accepted values `1`, `2`, `3`, `1.ª`/`2.ª`/`3.ª`, `primera`/`segunda`/`tercera`, case-insensitive; empty = 1); an invalid value is a row error "La matrícula debe ser 1.ª, 2.ª o 3.ª.". Preview rows return `enrollment_number`. Docs say PDF rows import as 1.ª.
- Automatic advancement (`AdvanceApprovedStudents`) creates enrollments with 1.
- `config/teaching.php` action plan item "Tutorías adicionales obligatorias" → "Tutorías adicionales según el criterio del docente y del coordinador".

## Tasks

| ID | Specs | Scope | Route | Status | Commit |
|---|---|---|---|---|---|
| T1 | S1-S3 | Backend: migration, model, requests, resources, import, advancement, config, docs, tests | delegated (parallel writer, trigger a) | done | backend f1920ec |
| T2 | S1-S3 | Frontend: types, "Matrícula" select on enroll/edit forms, labels in lists, roster import note, tests | delegated (parallel writer, trigger a) | done | frontend d7265bb |

Risk: item 1 (migration adds a column to `inscripcion_tutoria`). Independent verifier after both units.

## Log

- **L1 (2026-11-15, user):** "Me pidieron arreglos: - Solo los estudiantes que cuenten con segunda matrícula deberían tener que asistir a tutorías de forma obligatoria - El proceso de valoración no debe entrar el porcentaje de la prueba diagnóstico; en el informe - El listado de los estudiantes debería estar en el orden: apellidos y nombres"
- **L2 (2026-11-15, user answers):** matrícula source: "Se registra al inscribir (Recomendado)"; diagnostic action plan: "Acá debemos cambiar, creo que eso sería más bien dependiendo lo que consideren los docentes y coordinadores"; valoración: "me refiero a donde salen las notas, ahi hace el calculo con la nota de diagnostico, pero solo debería ser con la nota del primero y segundo parcial, me parece como porcentaje el resultado".
- **L3:** Valoración fixed in frontend a78b4dc (grades "Evolución (P1 → P2)" uses only Parcial 1 and Parcial 2). Student lists: attendance endpoints sorted by name in backend 8c87db8; list headers renamed to "Apellidos y nombres" in frontend a78b4dc. Names stored before the two-field form may not be apellidos-first; sorting relies on stored order.
- **L4:** T1 backend done (542 passed, only the 2 known failures). Deviation: roster import is a PDF without matrícula, so the UI states imports start in 1.ª. Note: enrollment PATCH clears `phone` when absent (pre-existing), so the frontend always sends phone. Added: `POST tutoring-coordination/students` with `tutoring_id` accepts `enrollment_number`.
- **L5:** T2 frontend done (481 passed, tsc and lint clean). Teacher edits the matrícula in the "Calificaciones del estudiante" dialog (no other teacher edit form); re-enroll keeps the current matrícula. Parent fix: both PATCH endpoints share `ManageEnrollment::applyEdit`; a student with other roles can change matrícula, profile changes still 403 (test `test_the_matricula_of_a_student_with_other_roles_changes_but_their_profile_does_not`).
- **L6:** Independent verifier: S1, S2, S3 and contract pass, no blockers (50 rejected requests left data unchanged; PATCH differential identical for pure students). Advisories: `true` accepted as 1; no DB CHECK 1–3. Backend 543 passed (2 known failures), frontend 481 passed. Not applied to production.
