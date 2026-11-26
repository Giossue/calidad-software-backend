<?php

namespace Tests\Feature\Api\V1\Teacher;

class UppercaseTopicTest extends TeacherTestCase
{
    public function test_topic_names_are_stored_in_uppercase_and_duplicates_ignore_case(): void
    {
        $this->postJson($this->path('/topics'), ['name' => '  introducción   a las pruebas '])
            ->assertCreated()
            ->assertJsonPath('data.name', 'INTRODUCCIÓN A LAS PRUEBAS');

        $this->postJson($this->path('/topics'), ['name' => 'Introducción a las pruebas'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
        $this->assertDatabaseCount('tema', 1);
    }
}
