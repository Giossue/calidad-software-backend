<?php

namespace Tests\Feature\Api\V1\Tutoring;

use App\Models\Modalidad;
use App\Models\Subject;
use Laravel\Sanctum\Sanctum;

/** Toda asignatura tiene modalidad: no existe «Sin modalidad». */
class SubjectModalityTest extends TutoringTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs($this->coordinator, ['*']);
    }

    public function test_a_subject_cannot_be_created_without_a_modality(): void
    {
        $this->postJson(self::API.'/subjects', ['career_id' => $this->career->getKey(), 'name' => 'Calidad'])
            ->assertUnprocessable()->assertJsonValidationErrors('modality_id');
        $this->postJson(self::API.'/subjects', ['career_id' => $this->career->getKey(), 'name' => 'Calidad', 'modality_id' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('modality_id');

        $this->assertSame(0, Subject::query()->count());
    }

    public function test_a_subject_is_created_with_the_chosen_modality(): void
    {
        $virtual = Modalidad::query()->create(['nombre' => 'Virtual', 'estado' => true]);

        $this->postJson(self::API.'/subjects', ['career_id' => $this->career->getKey(), 'name' => 'Calidad', 'modality_id' => $virtual->getKey()])
            ->assertCreated()->assertJsonPath('data.modality_id', $virtual->getKey())->assertJsonPath('data.modality_name', 'Virtual');
    }

    public function test_the_modality_of_a_subject_cannot_be_removed(): void
    {
        $subject = $this->createSubject();
        $url = self::API.'/subjects/'.$subject->getKey();

        $this->patchJson($url, ['modality_id' => null])->assertUnprocessable()->assertJsonValidationErrors('modality_id');
        $this->patchJson($url, ['modality_id' => ''])->assertUnprocessable()->assertJsonValidationErrors('modality_id');
        $this->patchJson($url, ['name' => 'Otro nombre'])->assertOk()->assertJsonPath('data.modality_id', $this->modality->getKey());

        $this->assertSame($this->modality->getKey(), $subject->refresh()->modality_id);
    }

    public function test_the_malla_import_fails_when_the_career_has_no_modality(): void
    {
        $this->career->forceFill(['fk_modalidad' => null])->save();

        $this->postJson(self::API.'/curricula', ['career_id' => $this->career->getKey(), 'subjects' => [['code' => 'A-1', 'name' => 'Algoritmos', 'cycle' => 1, 'previous' => []]]])
            ->assertUnprocessable()->assertJsonValidationErrors(['career_id' => 'La carrera no tiene modalidad; pide al administrador que la registre.']);

        $this->assertSame(0, Subject::query()->count());
    }
}
