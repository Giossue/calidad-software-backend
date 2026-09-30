<?php

namespace Database\Seeders;

use App\Models\AsignaturaTutoria;
use App\Models\InscripcionTutoria;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

/**
 * Datos de demostración: 30 estudiantes matriculados en la tutoría "Calidad de Software" y 6 más
 * del mismo paralelo que todavía no están matriculados (para probar la búsqueda de «Registrar estudiante»).
 *
 * No forma parte de DatabaseSeeder a propósito (no debe correr en producción). Ejecutar con:
 *   php artisan db:seed --class=DemoStudentsSeeder
 *
 * Es idempotente: se puede correr varias veces sin duplicar usuarios ni inscripciones.
 * Acceso: estudiante01@mail.com … estudiante30@mail.com (matriculados) y pendiente01@mail.com … pendiente06@mail.com
 * (sin matricular), todos con password123.
 */
class DemoStudentsSeeder extends Seeder
{
    private const TUTORING_NAME = 'Calidad de Software';

    /** @var list<string> */
    private const NAMES = [
        'Andrade Loor Sofía', 'Arias Mera Kevin', 'Bravo Cedeño Daniela', 'Cabrera Zambrano Luis', 'Castro Vera Mariana',
        'Cedeño Intriago Jorge', 'Chávez Pilay Camila', 'Cruz Macías Andrés', 'Delgado Mora Valeria', 'Espinoza Ruiz Diego',
        'Flores Quiroz Paula', 'García Toala Mateo', 'Guerrero Alava Nicole', 'Herrera Zamora Sebastián', 'Intriago Solórzano Ariana',
        'Jiménez Párraga Bryan', 'Lara Mendoza Fernanda', 'Loor Villacís Carlos', 'Macías Pinargote Gabriela', 'Mera Cevallos Esteban',
        'Molina Basurto Lucía', 'Navarrete Rojas Pablo', 'Ortiz Briones Emily', 'Palma Zúñiga Joel', 'Pérez Alcívar Romina',
        'Quiroz Saltos Cristian', 'Ramírez Cobos Melany', 'Salazar Muñoz Adrián', 'Toala Franco Isabella', 'Zambrano Bazurto Ricardo',
    ];

    /** @var list<string> Del paralelo, pero sin matricular. */
    private const PENDING_NAMES = [
        'Acosta Burgos Tamara', 'Barberán Lucas Samuel', 'Cevallos Ponce Antonella', 'Dueñas Cañarte Martín', 'Escobar Rivas Julieta', 'Fernández Loor Santiago',
    ];

    public function run(): void
    {
        $tutoring = AsignaturaTutoria::query()->where('nombre', self::TUTORING_NAME)->first();

        if (! $tutoring) {
            $this->command?->error('No existe la tutoría "'.self::TUTORING_NAME.'". Créala primero y vuelve a ejecutar.');

            return;
        }

        foreach (self::NAMES as $index => $name) {
            $number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);

            $student = Usuario::query()->where('correo', "estudiante{$number}@mail.com")->first()
                ?? Usuario::factory()->create([
                    'nombre' => $name,
                    'correo' => "estudiante{$number}@mail.com",
                    'password_hash' => bcrypt('password123'),
                ]); // la fábrica genera cédula válida, teléfono único, correo verificado y rol estudiante

            if ($tutoring->fk_paralelo) {
                $student->paralelos()->syncWithoutDetaching([
                    $tutoring->fk_paralelo => ['fecha_asignacion' => now()->toDateString(), 'estado' => true],
                ]);
            }

            InscripcionTutoria::query()->firstOrCreate(
                ['fk_asig_tutoria' => $tutoring->getKey(), 'fk_id_usuario' => $student->getKey()],
                ['fecha_inscripcion' => now()->toDateString(), 'estado' => true],
            );
        }

        foreach (self::PENDING_NAMES as $index => $name) {
            $number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $student = Usuario::query()->where('correo', "pendiente{$number}@mail.com")->first()
                ?? Usuario::factory()->create(['nombre' => $name, 'correo' => "pendiente{$number}@mail.com", 'password_hash' => bcrypt('password123')]);

            if ($tutoring->fk_paralelo) {
                $student->paralelos()->syncWithoutDetaching([
                    $tutoring->fk_paralelo => ['fecha_asignacion' => now()->toDateString(), 'estado' => true],
                ]);
            }
        }

        $this->command?->info('30 estudiantes matriculados en "'.self::TUTORING_NAME.'" y '.count(self::PENDING_NAMES).' disponibles sin matricular.');
    }
}
