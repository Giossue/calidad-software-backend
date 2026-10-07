<?php

namespace App\Imports;

use App\Imports\Importers\AcademicPeriodsImporter;
use App\Imports\Importers\CareersImporter;
use App\Imports\Importers\CyclesImporter;
use App\Imports\Importers\FacultiesImporter;
use App\Imports\Importers\SectionsImporter;
use App\Imports\Importers\SubjectsImporter;

class ImporterRegistry
{
    /** @var array<string, class-string<Importer>> */
    public const TYPES = [
        'faculties' => FacultiesImporter::class,
        'careers' => CareersImporter::class,
        'cycles' => CyclesImporter::class,
        'academic-periods' => AcademicPeriodsImporter::class,
        'subjects' => SubjectsImporter::class,
        'sections' => SectionsImporter::class,
    ];

    public function resolve(string $type): Importer
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);

        return app(self::TYPES[$type]);
    }
}
