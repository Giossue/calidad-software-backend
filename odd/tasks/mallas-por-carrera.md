# Mallas por carrera (modelo de sílabos)

Objective: a malla is the career itself (as in proyecto-grado-silabos); uploading the malla creates the career's subjects. Repos: calidad-software-backend + calidad-software-frontend, branch `main` (team pushes to main). Delivery: work-unit commits per repo, push on user request. Runners: `php artisan test` (SQLite, DB_* env unset), `npx vitest run`.

## Specs

- **S1 — Carrera = malla.** "tener una malla distinta se considera como una nueva "carrera" por así decirlo, porque el código de carrera cambio, y es como si fuera aislado por así decirlo uno del otro". A career has a code. A second malla is registered as another career with its own code (the same name is allowed when the code differs); careers with the same name are shown as "Nombre · código" where they could be confused.
- **S2 — Quién.** "EL admin crea las carreras, y digamos que es el coordinador quien sube las mallas". The admin enters the career code when creating or editing a career. The career coordinator uploads the malla of their career.
- **S3 — Subir la malla crea las asignaturas.** "para mi no tiene mucho sentido que cuando suba la malla luego tenga que crear asignaturas". Uploading the malla (file → per-row preview with errors → import) creates the career's subjects, cycles and prerequisites in one step. The coordinator manages the malla and its subjects from a single "Malla curricular" screen (import, add/edit/deactivate a subject, prerequisites); the separate "Asignaturas" entry disappears.
- **S4 — Mismas columnas.** "las que tiene el mismo sistema nomás, no creemos más cosas por favor". The file keeps the current columns `codigo`, `nombre`, `ciclo`, `previas` (Excel/CSV, plus the current PDF reading). No new subject fields.
- **S5 — Sin versiones.** The curriculum-version mechanism (PAO from which it applies, "hasta qué ciclo", students pinned to a malla, "Eliminar esta malla") is removed. The migration refuses to run if any career has more than its initial malla, so no data is lost silently.
- **S6 — Malla visual como en sílabos (L7).** "cambiemos estas cosas, mejor usemos como tengo el del coordinador, que uso vueflow para eso, ya no como está ahora y que se vea como tengo también en coordinador, no del todo porque no uso todos los campos, sino los que ya tengo". The "Malla curricular" screen becomes a flow canvas like silabos COR-13 (`CurriculumCanvas` with Vue Flow): cycle columns, subject nodes, prerequisite arrows, legend and navigation controls; "Editar malla" mode to add a subject inside a cycle, edit it and connect prerequisites. Only the fields this system already has (code, name, cycle, prerequisites); no new fields (no hours, credits, unit, corequisites, order).

## Tasks

| ID | Specs | Scope | Route | Status | Commit |
|---|---|---|---|---|---|
| T1 | S1,S2 | Career code: backend (migration, requests, resources, career import) + frontend admin (form, list, detail) and "Nombre · código" labels | delegated (parallel writer, trigger a) | done | backend d94b0ba, frontend 5b474df |
| T2 | S2-S5 | Malla import into the career + remove versions: backend (migration, ManageCurriculum, routes, Curricula callers) + frontend single Malla screen | delegated (parallel writer, trigger a) | done | backend 4999362, frontend 2268c96 |
| T3 | S6,S3 | Flow canvas for the Malla screen (React Flow, port of silabos Vue Flow canvas) | delegated (writer, context backstop) | done | frontend 8de69cd |

Risk: item 1 (migration drops `curricula`, `subjects.curriculum_id`, `usuario.curriculum_id`) and item 3 (API change of `tutoring-coordination/curricula`, career unique name). Independent verifier after both units.

## Log

- **L1 (2026-11-11, user):** "olvídalo, mira, quiero cambiar un poco el enfoque: Actualmente como se maneja el tema de las mallas y materias? porque para mi no tiene mucho sentido que cuando suba la malla luego tenga que crear asignaturas, hagamos algo, revisa como lo tengo en: /home/giossue/Documentos/proyecto-grado-silabos-backend/, revisa primero eso para que entiendas"
- **L2 (2026-11-11, user, earlier):** "no no, hablo de tener más de una malla, pasa que a nivel institucional, tener una malla distinta se considera como una nueva "carrera" por así decirlo, porque el código de carrera cambio, y es como si fuera aislado por así decirlo uno del otro"
- **L3 (analysis):** Silabos has no malla entity: `carreras.codigo_carrera` (unique) is the malla; two mallas = two careers with the same name and different code; the coordinator imports the malla Excel (preview, then confirm), which creates the subjects; one Malla screen. Calidad today: `curricula` versions per career with `up_to_cycle` pinning, a read-only Malla page, subjects created in Asignaturas, no career code, career name globally unique.
- **L4 (2026-11-11, user):** "ok, hagamoslo 1. EL admin crea las carreras, y digamos que es el coordinador quien sube las mallas 2. las que tiene el mismo sistema nomás, no creemos más cosas por favor"
- **L5 (progress):** T1+T2 done. Seam check: backend 430/439 (3 pre-existing date/429 failures), frontend vitest 364 passed, tsc/build OK. Independent verifier: S1-S5 pass, no blockers; advisory fixed: career code stored uppercase. Migrations verified up/down/up on SQLite and on a throwaway PostgreSQL database (dropped). Pending: run the migration on the local DB (backup first), push on user request. Known gap: no subject reactivation endpoint; CatalogLookup resolves careers by name in imports (ambiguous if two careers share a name).
- **L6 (2026-11-11, user):** "dejame probar, abre el navegador el rol de navegador"
- **L7 (2026-11-11, user):** "cambiemos estas cosas, mejor usemos como tengo el del coordinador, que uso vueflow para eso, ya no como está ahora y que se vea como tengo también en coordinador, no del todo porque no uso todos los campos, sino los que ya tengo"
