<?php

namespace Tests\Feature\Api\V1\Tutoring;

use App\Models\Malla;
use App\Models\Subject;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

/**
 * Asignaturas por malla: subir el archivo y agregar asignaturas van a la malla
 * elegida; códigos, nombres y previas se resuelven dentro de esa malla.
 */
class MallaCurriculumTest extends TutoringTestCase
{
    private Malla $current;

    private Malla $next;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createCycle($this->career, $this->cycle->paralelo, 2);
        $this->current = Malla::query()->where('career_id', $this->career->getKey())->where('is_current', true)->firstOrFail();
        $this->next = Malla::query()->create(['career_id' => $this->career->getKey(), 'name' => 'Malla 2025', 'is_current' => false]);
        Sanctum::actingAs($this->coordinator, ['*']);
    }

    private function subjectPayload(Malla $malla, array $overrides = []): array
    {
        return ['malla_id' => $malla->getKey(), 'code' => 'SW-1', 'name' => 'Algoritmos', 'cycle_number' => 1, 'modality_id' => $this->modality->getKey(), ...$overrides];
    }

    public function test_the_preview_and_the_import_need_a_malla_of_the_career(): void
    {
        $csv = "codigo;nombre;ciclo;previas\nSW-1;Algoritmos;1;\n";
        $foreign = Malla::query()->where('career_id', $this->otherCareer->getKey())->firstOrFail();
        $file = fn () => UploadedFile::fake()->createWithContent('malla.csv', $csv);

        $this->post(self::API.'/curricula/preview', ['file' => $file(), 'career_id' => $this->career->getKey()], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('malla_id');
        $this->post(self::API.'/curricula/preview', ['file' => $file(), 'career_id' => $this->career->getKey(), 'malla_id' => $foreign->getKey()], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors(['malla_id' => 'La malla debe ser de la carrera elegida.']);
        $this->postJson(self::API.'/curricula', ['career_id' => $this->career->getKey(), 'subjects' => [['code' => 'SW-1', 'name' => 'Algoritmos', 'cycle' => 1]]])
            ->assertUnprocessable()->assertJsonValidationErrors('malla_id');
        $this->postJson(self::API.'/curricula', ['career_id' => $this->career->getKey(), 'malla_id' => $foreign->getKey(), 'subjects' => [['code' => 'SW-1', 'name' => 'Algoritmos', 'cycle' => 1]]])
            ->assertUnprocessable()->assertJsonValidationErrors(['malla_id' => 'La malla debe ser de la carrera elegida.']);
        $this->assertSame(0, Subject::query()->count());
    }

    public function test_importing_into_another_malla_repeats_codes_and_resolves_previous_subjects_inside_it(): void
    {
        $old = $this->createSubject($this->cycle, 'SW-1', 'Algoritmos');
        $this->createSubject($this->cycle, 'SW-OLD', 'Solo en la vigente');
        $csv = "codigo;nombre;ciclo;previas\nSW-1;Algoritmos;1;\nSW-2;Programación;2;SW-1\nSW-3;Redes;2;SW-OLD\n";

        $rows = $this->post(self::API.'/curricula/preview', [
            'file' => UploadedFile::fake()->createWithContent('malla.csv', $csv), 'career_id' => $this->career->getKey(), 'malla_id' => $this->next->getKey(),
        ], ['Accept' => 'application/json'])->assertOk()->json('data.subjects');
        $this->assertSame([], $rows[0]['errors'], 'El código de la malla vigente se repite en la nueva.');
        $this->assertSame([], $rows[1]['errors']);
        $this->assertSame(['La previa «SW-OLD» no está en la malla.'], $rows[2]['errors']);

        // En la misma malla el código y el nombre ya existen.
        $same = $this->post(self::API.'/curricula/preview', [
            'file' => UploadedFile::fake()->createWithContent('malla.csv', "codigo;nombre;ciclo;previas\nSW-1;Otra;1;\nSW-9;Algoritmos;1;\n"), 'career_id' => $this->career->getKey(), 'malla_id' => $this->current->getKey(),
        ], ['Accept' => 'application/json'])->assertOk()->json('data.subjects');
        $this->assertSame(['El código ya existe en la malla.'], $same[0]['errors']);
        $this->assertSame(['La asignatura ya existe en la malla.'], $same[1]['errors']);

        $subjects = array_map(fn (array $row): array => collect($row)->except('errors')->all(), array_slice($rows, 0, 2));
        $this->postJson(self::API.'/curricula', ['career_id' => $this->career->getKey(), 'malla_id' => $this->next->getKey(), 'subjects' => $subjects])
            ->assertCreated()->assertExactJson(['created' => 2]);

        $created = Subject::query()->where('malla_id', $this->next->getKey())->orderBy('id')->get();
        $this->assertSame(['SW-1', 'SW-2'], $created->pluck('code')->all());
        $this->assertSame([$this->career->getKey()], $created->pluck('career_id')->unique()->values()->all());
        $this->assertSame(['ALGORITMOS'], $created[1]->previousSubjects()->pluck('name')->all());
        $this->assertSame($created[0]->getKey(), $created[1]->previousSubjects()->value('subjects.id'), 'La previa es la de su malla, no la de la vigente.');
        $this->assertSame([], $old->nextSubjects()->pluck('subjects.id')->all());
        $this->assertSame(2, Subject::query()->where('malla_id', $this->current->getKey())->count());
    }

    public function test_a_subject_is_created_in_a_malla_and_takes_its_career(): void
    {
        $this->postJson(self::API.'/subjects', $this->subjectPayload($this->current))->assertCreated();

        $id = $this->postJson(self::API.'/subjects', $this->subjectPayload($this->next))->assertCreated()
            ->assertJsonPath('data.malla_id', $this->next->getKey())
            ->assertJsonPath('data.malla_name', 'MALLA 2025')
            ->assertJsonPath('data.career_id', $this->career->getKey())
            ->assertJsonPath('data.code', 'SW-1')
            ->json('data.id');
        $this->assertDatabaseHas('subjects', ['id' => $id, 'malla_id' => $this->next->getKey(), 'career_id' => $this->career->getKey()]);

        $this->postJson(self::API.'/subjects', $this->subjectPayload($this->next, ['name' => 'Otra']))
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson(self::API.'/subjects', $this->subjectPayload($this->next, ['code' => 'SW-9']))
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson(self::API.'/subjects', collect($this->subjectPayload($this->next, ['code' => 'SW-7', 'name' => 'Sin malla']))->except('malla_id')->all())
            ->assertUnprocessable()->assertJsonValidationErrors('malla_id');
        $foreign = Malla::query()->where('career_id', $this->otherCareer->getKey())->firstOrFail();
        $this->postJson(self::API.'/subjects', $this->subjectPayload($foreign, ['code' => 'X-1', 'name' => 'Ajena']))->assertForbidden();
        $this->assertSame(2, Subject::query()->where('code', 'SW-1')->count());
        $this->assertSame(0, Subject::query()->where('career_id', $this->otherCareer->getKey())->count());
    }

    public function test_subjects_are_filtered_by_malla_and_previous_subjects_stay_in_the_malla(): void
    {
        $inCurrent = $this->createSubject($this->cycle, 'SW-1', 'Algoritmos');
        $inNext = Subject::query()->create(['malla_id' => $this->next->getKey(), 'code' => 'SW-1', 'name' => 'ALGORITMOS', 'cycle_number' => 1, 'modality_id' => $this->modality->getKey(), 'is_active' => true]);
        $nextSecond = Subject::query()->create(['malla_id' => $this->next->getKey(), 'code' => 'SW-2', 'name' => 'PROGRAMACION', 'cycle_number' => 2, 'modality_id' => $this->modality->getKey(), 'is_active' => true]);

        $this->getJson(self::API.'/subjects?malla_id='.$this->next->getKey())->assertOk()
            ->assertJsonPath('data.*.id', [$inNext->getKey(), $nextSecond->getKey()])
            ->assertJsonPath('data.*.malla_name', ['MALLA 2025', 'MALLA 2025']);
        $this->getJson(self::API.'/subjects?malla_id='.$this->current->getKey())->assertOk()
            ->assertJsonPath('data.*.id', [$inCurrent->getKey()])
            ->assertJsonPath('data.0.malla_id', $this->current->getKey());
        $this->getJson(self::API.'/subjects?career_id='.$this->career->getKey())->assertOk()->assertJsonCount(3, 'data');

        $this->patchJson(self::API."/subjects/{$inNext->id}", ['next_subject_ids' => [$nextSecond->id]])->assertOk()
            ->assertJsonPath('data.next_subjects.0.id', $nextSecond->getKey());
        $this->patchJson(self::API."/subjects/{$inCurrent->id}", ['next_subject_ids' => [$nextSecond->id]])
            ->assertUnprocessable()->assertJsonValidationErrors(['next_subject_ids.0' => 'La asignatura que continúa debe pertenecer a la misma malla.']);
        $this->assertSame([], $inCurrent->nextSubjects()->pluck('subjects.id')->all());
    }
}
