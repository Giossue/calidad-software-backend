<?php

namespace Database\Seeders;

use App\Models\Modalidad;
use Illuminate\Database\Seeder;

class ModalidadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modalidades = [
            'Presencial',
            'Virtual',
            'Híbrida',
        ];

        foreach ($modalidades as $nombre) {
            Modalidad::updateOrCreate(
                ['nombre' => $nombre],
                ['estado' => true]
            );
        }
    }
}
