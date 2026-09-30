<?php

namespace Tests\Feature\Api\V1\Teacher;

use App\Models\Usuario;
use Laravel\Sanctum\Sanctum;

class TeacherAccessTest extends TeacherTestCase
{
    public function test_only_teachers_can_use_the_workspace(): void
    {
        foreach (['administrador', 'estudiante', 'coordinador_carrera', 'coordinador_titulacion'] as $role) {
            Sanctum::actingAs(Usuario::factory()->withRole($role)->create(), ['access-api']);
            $this->getJson('/api/v1/teacher/tutorings')->assertForbidden();
            $this->postJson($this->path('/topics'), ['name' => 'Intento'])->assertForbidden();
        }
    }

    public function test_challenge_tokens_inactive_and_unverified_accounts_are_denied(): void
    {
        Sanctum::actingAs($this->teacher, ['two-factor:challenge']);
        $this->getJson('/api/v1/teacher/tutorings')->assertForbidden();
        Sanctum::actingAs($this->teacher, ['access-api']);
        $this->teacher->update(['estado' => false]);
        $this->getJson('/api/v1/teacher/tutorings')->assertForbidden();
        $this->teacher->update(['estado' => true, 'email_verified_at' => null]);
        $this->getJson('/api/v1/teacher/tutorings')->assertForbidden();
    }

    public function test_only_assigned_tutorings_are_listed_with_search_and_pagination(): void
    {
        $foreign = $this->createTutoring($this->otherCycle);
        $foreign->update(['fk_docente' => Usuario::factory()->withRole('docente')->create()->getKey()]);
        $this->getJson('/api/v1/teacher/tutorings?per_page=1&search=Calidad')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->tutoring->getKey())->assertJsonPath('data.0.can_manage', true)->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/teacher/tutorings?search=inexistente')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_other_teachers_tutorings_cannot_be_read_or_modified(): void
    {
        $foreign = $this->createTutoring($this->otherCycle);
        $foreign->update(['fk_docente' => Usuario::factory()->withRole('docente')->create()->getKey()]);
        foreach (['/students', '/available-students', '/topics', '/attendance', '/sessions', '/reports'] as $suffix) {
            $this->getJson($this->path($suffix, $foreign))->assertForbidden();
        }
        $this->postJson($this->path('/topics', $foreign), ['name' => 'Ajeno'])->assertForbidden();
        $this->postJson($this->path('/reports', $foreign), ['title' => 'Ajeno'])->assertForbidden();
        $this->putJson($this->path('/sessions', $foreign), [])->assertForbidden();
        $this->postJson($this->path('/students', $foreign), [])->assertForbidden();
    }

    public function test_inactive_tutorings_and_periods_preserve_read_access_and_deny_writes(): void
    {
        foreach (['tutoring', 'period'] as $target) {
            $this->tutoring->update(['estado' => $target !== 'tutoring']);
            $this->period->update(['estado' => $target !== 'period']);
            $this->getJson($this->path('/students'))->assertOk();
            $this->getJson('/api/v1/teacher/tutorings')->assertJsonPath('data.0.can_manage', false);
            $this->postJson($this->path('/topics'), ['name' => 'Nuevo'])->assertForbidden();
        }
    }

    public function test_reassignment_revokes_previous_teachers_scope_immediately(): void
    {
        $next = Usuario::factory()->withRole('docente')->create();
        $this->tutoring->update(['fk_docente' => $next->getKey()]);
        $this->getJson($this->path('/students'))->assertForbidden();
        $this->postJson($this->path('/topics'), ['name' => 'Nuevo'])->assertForbidden();
        Sanctum::actingAs($next, ['access-api']);
        $this->getJson($this->path('/students'))->assertOk();
    }
}
