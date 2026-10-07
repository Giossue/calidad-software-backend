<?php

namespace App\Imports;

use App\Models\Usuario;

interface Importer
{
    /**
     * Columnas del CSV: nombre normalizado => obligatoria.
     *
     * @return array<string, bool>
     */
    public function columns(): array;

    /**
     * Fila de ejemplo para la plantilla descargable.
     *
     * @return array<string, string>
     */
    public function example(): array;

    public function authorize(Usuario $user): bool;

    /**
     * Registra una fila. Lanza ValidationException, AuthorizationException o
     * HttpException cuando la fila no puede registrarse.
     *
     * @param  array<string, string|null>  $row
     */
    public function import(array $row, Usuario $user): void;
}
