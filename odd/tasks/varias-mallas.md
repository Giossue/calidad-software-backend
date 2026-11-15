# Varias mallas por carrera

Objective: a career can have several mallas at the same time so people are not registered again under a new career; the coordinator works with one malla at a time and each student follows one malla. Repos: calidad-software-backend + calidad-software-frontend, branch `main` (team pushes to main). Delivery: work-unit commits per repo, push on user request. Runners: `php artisan test` (SQLite, DB_* env unset), `npx vitest run`.

## Specs

- **S1 — Varias mallas en la misma carrera (L1, L3).** "me pidieron que se pueda trabajar con varias mallas … en el caso de las tutorías se puede crear más de una malla" / "quieren evitarse lo de crear una nueva carrera, porque eso sería lo mismo para registrar otra vez los usuarios como docentes, estudiantes, y así". A career has one or more mallas (name, e.g. "Malla 2025"), exactly one marked **vigente**. Teachers, coordinators and students stay linked to the career, so nobody is registered again. This reopens the 2026-11-11 decision "malla = carrera" (odd/tasks/mallas-por-carrera.md S1/S5) for mallas inside one career; careers keep their code.
- **S2 — Cada estudiante sigue su malla (L4).** Answer: "Cada uno tiene la suya" — existing students stay on the previous malla; new students (registration, imports, coordinator, admin) go to the career's vigente malla; the coordinator can move a student to another malla of the same career.
- **S3 — El coordinador trabaja con una malla a la vez (L4).** Answer: "Elige una malla a la vez" — next to the career, a malla selector (vigente by default) on Malla curricular, Tutorías, Estudiantes and Reportes; everything shown and created there belongs to that malla.
- **S4 — Sin equivalencias (L4).** Answer: "No, son independientes" — what a student passed in one malla does not count in another (prerequisites, pending subjects and automatic advancement stay inside one malla).
- **S5 — Gestión de mallas.** The career coordinator creates a malla (name, optionally vigente), renames it, marks it vigente, and deletes it only while it has no subjects, students or tutorings and is not the vigente one. A new career (admin) gets a vigente "Malla inicial" automatically. Uploading the malla file and adding subjects go into the selected malla.
- **S6 — Datos existentes.** The migration creates one vigente "Malla inicial" per existing career and assigns to it all the career's subjects and students. No data is lost; `down()` restores the previous schema only while every career has a single malla.

Out of scope: titulación rule (backend fixed cycle 6 vs frontend last cycle) — pending separate decision; cycle rows (`ciclo`) stay per career.

## API contract (backend ↔ frontend)

All under `/api/v1/tutoring-coordination`, authorized like the rest of the coordinator API (career coordinator of that career).

- `GET careers` — each career adds `mallas: [{ id, name, is_current }]` (ordered: vigente first, then by name).
- `GET mallas?career_id=` → `data: [{ id, career_id, name, is_current, subjects_count, students_count, tutorings_count }]`.
- `POST mallas` `{ career_id, name, is_current?: boolean }` → 201 malla. Name required, max 100, unique within the career ("Ya existe una malla con ese nombre en la carrera.").
- `PATCH mallas/{malla}` `{ name?, is_current?: true }` → malla. Marking vigente unmarks the previous one. `is_current: false` is rejected (another malla must be marked instead).
- `DELETE mallas/{malla}` → 204; 422 "La malla tiene asignaturas, estudiantes o tutorías." or "No se puede eliminar la malla vigente.".
- `POST curricula/preview` and `POST curricula`: add required `malla_id` (must belong to `career_id`); codes/names unique and prerequisites resolved within that malla.
- `GET subjects?malla_id=` filter; `POST subjects` requires `malla_id` (career taken from the malla); subject payloads add `malla_id`, `malla_name`. Prerequisites must be subjects of the same malla.
- `GET tutorings?malla_id=` filter (malla of the tutoring's subject); tutoring payloads add `malla_id`, `malla_name`. `POST tutorings`: the subject must belong to a malla of the cycle's career (unchanged rule) — the frontend only offers subjects of the selected malla.
- `GET students?malla_id=` filter; student payloads add `malla_id`, `malla_name`.
- `PATCH students/{student}/malla` `{ malla_id }` → student. The malla must belong to the student's career; 422 "Retira primero sus tutorías activas de la otra malla." when the student has active enrollments in tutorings of another malla in the active period.
- `GET reports?malla_id=` filter.
- `POST students` accepts optional `malla_id` (must belong to the career, 422 "La malla no pertenece a la carrera."; default vigente). `GET degree-students?malla_id=` filter. Curriculum duplicate messages say "ya existe en la malla". (Added after T2 found these gaps.)
- Enrollment: a student can only be enrolled in tutorings of their malla (coordinator, teacher, available-students lists, available-tutorings, automatic advancement, roster import): 422 "El estudiante sigue otra malla." where an explicit enrollment is attempted; lists simply exclude them.
- New students created through a tutoring (teacher or coordinator panel "estudiante nuevo", roster import) get that tutoring's malla; created anywhere else they get the career's vigente malla. Changing a student's career (admin, profile completion) sets the new career's vigente malla.

## Tasks

| ID | Specs | Scope | Route | Status | Commit |
|---|---|---|---|---|---|
| T1 | S1-S6 | Backend: `malla` table + migration/backfill, models, malla API, curriculum/subjects/tutorings/students/reports per malla, enrollment rule, student malla assignment, imports, docs | delegated (parallel writer, trigger a) | done | backend d283673 |
| T2 | S1-S5 | Frontend: malla selector next to career, Malla curricular malla management, Tutorías/Estudiantes/Reportes filtered by malla, "Cambiar de malla" for a student | delegated (parallel writer, trigger a) | done | frontend 210b390 |

Risk: item 1 (migration adds `malla`, `subjects.malla_id`, `usuario.malla_id` and backfills existing data; changes subject uniqueness) and item 3 (coordinator API: `curricula` and `subjects` now require `malla_id`). Independent verifier after both units.

## Log

- **L1 (2026-11-15, user):** "Otro arreglo es que me pidieron que se pueda trabajar con varias mallas, me dijeron algo de que en el caso de las tutorías se puede crear más de una malla y ahí no sé como hacerlo, cómo para que el coordinador elija como con que malla trabajar, es medio raro eso, pero primero analiza eso"
- **L2:** Analysis: today each malla is its own career (2026-11-11 decision); the coordinator already picks a career on every screen. Versions (curricula) were removed in backend 4999362.
- **L3 (2026-11-15, user):** "lo que pasa es que quieren evitarse lo de crear una nueva carrera, porque eso sería lo mismo para registrar otra vez los usuarios como docentes, estudiantes, y así"
- **L4 (2026-11-15, user answers):** students: "Cada uno tiene la suya (Recomendado)"; coordinator: "Elige una malla a la vez (Recomendado)"; equivalence: "No, son independientes".
- **L5:** Independent verifier: backend S1–S6 pass; 3 frontend blockers (no `malla_id` on student registration, Titulación tab, roster import) fixed in one correction batch. PostgreSQL check on a throwaway restore of the production backup: 28 mallas (one vigente per career), 1184/1184 subjects and all students assigned, no cross-career links; down/up OK. Suites: backend 523 passed (only the 2 base failures), frontend 463 passed. Local DB migrated (backup before-mallas-202611151312.dump); browser check as coordinator: "Malla inicial · Vigente", "Nueva malla" creates and selects "Malla 2025". Not applied to production. Open: moved students keep pending subjects of the old malla; bulk subject import goes to the vigente malla.
- **L6 (2026-11-15, user, relaying teammates):** Q1 moved student's pending subjects: "sería el caso b creería yo pero son consultas que toca hacer con la ING Galuth o Danilo" → keep as history (current behavior), pending confirmation. Q2 bulk subject import malla column: "dejemoslo pendiente". Q3 titulación: "si creo yo que sería el caso b, igual eso no hace daño a nadie, Si quieren ir proponiendo temas de titulación se ponen y ya" → titulación from cycle 6 for every career; frontend aligned with backend `teaching.degree_from_cycle` in frontend 5fa744c.
