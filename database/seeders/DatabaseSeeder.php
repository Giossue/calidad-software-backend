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

        // 4. Período Académico Vigente
        PeriodoAcademico::firstOrCreate(
            ['nombre' => 'PAO 2026-1'],
            [
                'fecha_inicio' => '2026-05-01',
                'fecha_fin' => '2026-09-30',
                'estado' => true,
            ]
        );
    }
}
