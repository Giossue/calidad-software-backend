# Trabajo por períodos académicos

Objective: organize daily work around the single active academic period (as in proyecto-grado-silabos) and record each student's cycle per period automatically. Repos: calidad-software-backend + calidad-software-frontend, branch `main`. Delivery: work-unit commits; push on user request. Runners: `php artisan test` (SQLite, DB_* env unset), `npx vitest run`.

## Specs

- **S1 — Un solo período vigente.** "todo debería ser por periodos académicos pues, porque entiendo que solo puede haber un periodo vigente". The database guarantees at most one active `periodo_academico` (today only code does). The admin keeps creating and activating periods.
- **S2 — El trabajo diario es del período vigente.** Coordinator work screens show the active period: the Tutorías list shows the active period's tutorings by default (today it mixes all periods).
- **S3 — Ciclo del estudiante por período, automático.** Each student has one record per period (cycle and section in that period). It is filled automatically whenever the student ends up enrolled in tutorings of that period (automatic advancement, coordinator, teacher, roster import): the cycle of that period is the HIGHEST cycle among their active enrollments in that period (user confirmed: "ok veamos como queda" to the proposal "ciclo más alto"). `usuario.fk_ciclo` follows the active period's record so existing screens keep working; reaching the last cycle puts the student in titulación as today. Existing data is backfilled from current enrollments.
- **S4 — Sin cambio manual de ciclo.** The coordinator's "Cambiar ciclo" (⇄) disappears. A student with no cycle yet can still be given one ("Asignar ciclo"); once they have a cycle, it changes only through S3.
- **S5 — Historial por período.** Past periods can be consulted: the Tutorías list has a period filter (active by default, past periods read-only), and the student detail shows the cycle they had in each period.
- **S6 — Nada se copia al cambiar de período.** Malla, careers, teachers and sections are period-independent and simply persist; no copy step.

- **S7 — La oferta es del período (L6-L8).** "en un periodo están x tutorias, x paralelos, x modalidad, etc y claro, lo del anterior periodo no debería persistir". A subject belongs to the malla only (code, name, cycle number, prerequisites) and no longer carries paralelos: the "ciclo y paralelos" step of the subject form becomes just the cycle. Paralelo, modality, teacher and schedule are chosen when creating each tutoring of the period. When a new period becomes active the Tutorías list starts empty for it; previous periods stay in history only.
- **S8 — Título con el período (L8).** "pon en el título que se vea tutorías - periodo xxx y ahi pones el nombre como tal del periodo". The Tutorías screen title reads "Tutorías - Período <period name>" (e.g. "Tutorías - Período PAO II 2026"), using the shown period's real name.

## Tasks

| ID | Specs | Scope | Route | Status | Commit |
|---|---|---|---|---|---|
| T1 | S1,S3,S4,S5,S6,S7 | Backend: unique active period, `estudiante_periodo` table + backfill, automatic cycle on enrollment, coordinator cycle change limited to students without cycle, period filter and cycle history in API, subject cycle number without paralelos | delegated (parallel writer, trigger a) | done | backend 20f41e8 |
| T2 | S2,S4,S5,S7,S8 | Frontend: Tutorías period filter and title, remove ⇄ change-cycle, cycle-per-period in student detail, subject form without paralelos, tutoring creation picks paralelo | delegated (parallel writer, trigger a) | done | frontend 6df18e4 |

Risk: item 1 (new table + backfill, unique index on periods) and item 3 (API contract of tutorings list, catalogs, student cycle endpoint). Independent verifier after both units.

## Log

- **L1 (2026-11-11, user):** "y no entiendo lo de cambiar ciclo, no se supone que es automático eso?"
- **L2 (2026-11-11, user):** "ok, pero como se haría para que sea automático? porque entiendo que sería cuando apruebe tutorias o cómo? eso no entiendo"
- **L3 (2026-11-11, user):** "es que todo debería ser por periodos académicos pues, porque entiendo que solo puede haber un periodo vigente, porque mira, revisa como tengo mi sistema de silabos, ahi yo me manejo por periodos, para ver si con eso podemos mejorar algo"
- **L4 (analysis):** Silabos: no "active" flag on the period; one convocatoria per period (DB unique), work screens show only the current one, past ones in history pages with a period filter, structural data period-independent, per-period records snapshot their context. Calidad: `periodo_academico.estado` with no DB uniqueness (code-only `sincronizarVigencia`), tutorings list mixes periods, student cycle is a single overwritten `usuario.fk_ciclo`, nothing happens on period change except the flag, no history.
- **L5 (2026-11-11, user):** "ok veamos como queda" (approves the proposal: one active period in DB, daily work on the active period, per-period student cycle filled automatically with the highest cycle, remove ⇄, history by period, nothing copied).
- **L6 (2026-11-11, user):** "pero una cosa, si tienes en cuenta lo de que en un ciclo pueden haber las mismas tutorias, o sea no sé si me doy a entender con eso"
- **L7 (2026-11-11, user):** "es que sabes cual es el problema, es que en un periodo están x tutorias, x paralelos, x modalidad, etc y claro, lo del anterior periodo no debería persistir, a eso voy"
- **L8 (2026-11-11, user):** "ok hazlo y pon en el título que se vea tutorías - periodo xxx y ahi pones el nombre como tal del periodo y si ahi sigue"
- **L9 (progress):** T1+T2 done. Verifier: S1,S2,S4,S5,S6,S8 pass; blockers fixed in one correction batch (student paralelo kept: period cycle uses the enrolled tutoring's paralelo row; tutoring creation offers catalog paralelos + new paralelo). Final: backend 450/458 (2 baseline failures), frontend 405 passed, tsc/lint/build OK. Migrations verified up/down/up on SQLite and a throwaway PostgreSQL DB (dropped); DB rejects a second active period. Commits backend 20f41e8, frontend 6df18e4.
