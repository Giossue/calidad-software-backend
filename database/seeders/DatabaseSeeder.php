<?php

namespace Database\Seeders;

use App\Models\PeriodoAcademico;
use App\Models\Role;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with verified initial admin users.
     */
    public function run(): void
    {
        $adminRole = Role::firstOrCreate(['slug' => 'administrador'], ['name' => 'Administrador']);
        $coordRole = Role::firstOrCreate(['slug' => 'coordinador_titulacion'], ['name' => 'Coordinador de titulación']);

        // 1. Administrador Principal
        $adminPrincipal = Usuario::firstOrCreate(
            ['correo' => 'admin@sistema.com'],
            [
                'cedula' => '1234567890',
                'nombre' => 'Administrador del Sistema',
                'telefono' => '0999999999',
                'password_hash' => Hash::make('Admin123*'),
                'estado' => true,
                'email_verified_at' => now(),
            ]
        );
        $adminPrincipal->roles()->syncWithoutDetaching([$adminRole->id]);

        // 2. Administrador de Servidor / Pruebas
        $adminServidor = Usuario::firstOrCreate(
            ['correo' => 'admin@mail.com'],
            [
                'cedula' => '0201234567',
                'nombre' => 'Elvis Rimax Chela Tiamba',
                'telefono' => '0987654321',
                'password_hash' => Hash::make('Admin123*'),
                'estado' => true,
                'email_verified_at' => now(),
            ]
        );
        $adminServidor->roles()->syncWithoutDetaching([$adminRole->id]);

        // 3. Coordinador de Titulación
        $coordTitulacion = Usuario::firstOrCreate(
            ['correo' => 'titulacion@mail.com'],
            [
                'cedula' => '0201864329',
                'nombre' => 'Darwin Carrión Buenaño',
                'telefono' => '0980219332',
                'password_hash' => Hash::make('password123'),
                'estado' => true,
                'email_verified_at' => now(),
            ]
        );
        $coordTitulacion->roles()->syncWithoutDetaching([$coordRole->id]);

        // 4. Estudiante de Titulación
        $studentRole = Role::firstOrCreate(['slug' => 'estudiante'], ['name' => 'Estudiante']);
        $estudiante = Usuario::firstOrCreate(
            ['correo' => 'estudiante@mail.com'],
            [
                'cedula' => '0201999999',
                'nombre' => 'Carlos Estudiante de Prueba',
                'telefono' => '0981112233',
                'password_hash' => Hash::make('password123'),
                'estado' => true,
                'email_verified_at' => now(),
            ]
        );
        $estudiante->roles()->syncWithoutDetaching([$studentRole->id]);

        // 5. Período Académico Vigente
        $periodo = PeriodoAcademico::firstOrCreate(
            ['nombre' => 'PAO 2026-1'],
            [
                'fecha_inicio' => '2026-05-01',
                'fecha_fin' => '2026-09-30',
                'estado' => true,
            ]
        );

        // 6. Docente de Tutorías
        $docenteRole = Role::firstOrCreate(['slug' => 'docente'], ['name' => 'Docente']);
        $docente = Usuario::firstOrCreate(
            ['correo' => 'docente@mail.com'],
            [
                'cedula' => '0201555555',
                'nombre' => 'Ing. Docente de Tutoría',
                'telefono' => '0984443322',
                'password_hash' => Hash::make('password123'),
                'estado' => true,
                'email_verified_at' => now(),
            ]
        );
        $docente->roles()->syncWithoutDetaching([$docenteRole->id]);

        // 7. Asignatura e Inscripción de Tutoría para el Estudiante
        $modalidad = \App\Models\Modalidad::firstOrCreate(
            ['nombre' => 'Presencial'],
            ['estado' => true]
        );
        $paralelo = \App\Models\Paralelo::firstOrCreate(
            ['nombre' => 'A'],
            ['estado' => true]
        );

        $asignatura = \App\Models\AsignaturaTutoria::firstOrCreate(
            ['nombre' => 'Aseguramiento de la Calidad de Software', 'fk_periodo' => $periodo->getKey()],
            [
                'fk_modalidad' => $modalidad->getKey(),
                'fk_paralelo' => $paralelo->getKey(),
                'fk_docente' => $docente->getKey(),
                'estado' => true,
            ]
        );

        \App\Models\Horario::firstOrCreate(
            ['fk_asig_tutoria' => $asignatura->getKey(), 'dia_semana' => 'Miércoles'],
            [
                'hora_inicio' => '14:00:00',
                'hora_fin' => '16:00:00',
                'estado' => true,
            ]
        );

        \App\Models\InscripcionTutoria::firstOrCreate(
            ['fk_asig_tutoria' => $asignatura->getKey(), 'fk_id_usuario' => $estudiante->getKey()],
            [
                'fecha_inscripcion' => now()->toDateString(),
                'estado' => true,
            ]
        );
    }
}
