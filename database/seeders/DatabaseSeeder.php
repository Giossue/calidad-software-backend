<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Siembra el catálogo académico de la UEB y la única cuenta inicial del
     * sistema: el administrador. Los demás roles se crean como catálogo (para
     * poder asignarlos luego desde el panel) pero sin usuarios de demostración:
     * coordinadores, docentes y estudiantes se crean vía carga masiva o UI.
     */
    private const ADMIN_PASSWORD = 'Administrador123*';

    public function run(): void
    {
        $this->call(ModalidadSeeder::class);
        $this->call(UebCatalogSeeder::class);

        $adminRole = Role::firstOrCreate(['slug' => 'administrador'], ['name' => 'Administrador']);
        Role::firstOrCreate(['slug' => 'coordinador_titulacion'], ['name' => 'Coordinador de titulación']);
        Role::firstOrCreate(['slug' => 'coordinador_carrera'], ['name' => 'Coordinador de carrera']);
        Role::firstOrCreate(['slug' => 'docente'], ['name' => 'Docente']);
        Role::firstOrCreate(['slug' => 'estudiante'], ['name' => 'Estudiante']);

        $admin = Usuario::firstOrCreate(
            ['correo' => 'admin@email.com'],
            [
                'cedula' => '0000000000',
                'nombre' => 'Administrador del Sistema',
                'telefono' => '0999999999',
                'password_hash' => Hash::make(self::ADMIN_PASSWORD),
                'estado' => true,
                'email_verified_at' => now(),
            ]
        );
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);
    }
}
