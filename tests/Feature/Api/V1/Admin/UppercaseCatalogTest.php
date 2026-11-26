<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Carrera;
use App\Models\Facultad;
use App\Models\Malla;
use App\Models\Subject;
use App\Models\Tema;
use App\Models\TemaTitulacion;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UppercaseCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_faculty_names_are_stored_in_uppercase_and_duplicates_ignore_case(): void
    {
        Sanctum::actingAs(Usuario::factory()->withRole('administrador')->create(), ['*']);

        $this->postJson('/api/v1/faculties', ['name' => 'facultad de ingeniería'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'FACULTAD DE INGENIERÍA');
        $this->postJson('/api/v1/faculties', ['name' => 'Facultad de Ingeniería'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
        $this->assertDatabaseHas('facultad', ['nombre' => 'FACULTAD DE INGENIERÍA']);
    }

    public function test_names_catalogs_topics_and_titles_are_uppercased_whatever_the_entry_point(): void
    {
        $this->assertSame('PÉREZ LÓPEZ ANA SOFÍA', (new Usuario)->fill(['nombre' => 'Pérez  López ana sofía'])->nombre);
        $this->assertSame('CIENCIAS ADMINISTRATIVAS', (new Facultad)->fill(['nombre' => 'ciencias administrativas'])->nombre);
        $this->assertSame('INGENIERÍA DE SOFTWARE', (new Carrera)->fill(['nombre' => 'Ingeniería de Software'])->nombre);
        $this->assertSame('MALLA 2024', (new Malla)->fill(['name' => 'Malla 2024'])->name);
        $this->assertSame('CÁLCULO I', (new Subject)->fill(['name' => 'Cálculo I'])->name);
        $this->assertSame('PRUEBAS UNITARIAS', (new Tema)->fill(['nombre' => 'pruebas unitarias'])->nombre);
        $this->assertSame('SISTEMA DE TUTORÍAS', (new TemaTitulacion)->fill(['titulo' => 'Sistema de tutorías'])->titulo);
        // Las descripciones y los textos largos se guardan como se escriben.
        $this->assertSame('Conceptos básicos', (new Tema)->fill(['descripcion' => 'Conceptos básicos'])->descripcion);
    }
}
