<?php

namespace Tests\Feature;

use App\Models\Carrera;
use App\Models\Facultad;
use Database\Seeders\UebCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UebCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_catalog_seeder_stores_uppercase_names_and_can_run_again(): void
    {
        $this->seed(UebCatalogSeeder::class);
        $faculties = Facultad::query()->count();
        $careers = Carrera::query()->count();

        $this->seed(UebCatalogSeeder::class);

        $this->assertGreaterThan(0, $careers);
        $this->assertSame($faculties, Facultad::query()->count());
        $this->assertSame($careers, Carrera::query()->count());
        $this->assertTrue(Carrera::query()->where('nombre', 'SOFTWARE')->exists());
        $this->assertFalse(Facultad::query()->whereRaw('nombre <> upper(nombre)')->exists());
    }
}
