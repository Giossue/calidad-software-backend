<?php

namespace App\Imports\Importers;

use App\Actions\Users\CreateAccount;
use App\Imports\CatalogLookup;
use App\Imports\CsvReader;
use App\Imports\Importer;
use App\Models\Ciclo;
use App\Models\Usuario;
use App\Notifications\QueuedProvisionalPasswordNotification;
use App\Rules\CedulaEcuatoriana;
use App\Rules\CedulaOPasaporte;
use App\Support\StudentCycle;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Base de las importaciones de cuentas. Todas usan el mismo formato de columnas
 * (correo, cedula, nombres, apellidos, rol, facultad, carrera, ciclo, telefono);
 * cada pantalla define cuáles exige. Los datos personales ausentes los completa el
 * usuario al primer ingreso; los estudiantes quedan ubicados en su ciclo.
 */
abstract class AccountImporter implements Importer
{
    /** Orden de las columnas en la plantilla. */
    private const COLUMNS = ['correo', 'cedula', 'nombres', 'apellidos', 'rol', 'facultad', 'carrera', 'ciclo', 'telefono'];

    public function __construct(
        protected CatalogLookup $lookup,
        private CreateAccount $createAccount,
        private StudentCycle $cycles,
    ) {}

    /**
     * Rol que tendrá la cuenta creada a partir de la fila.
     *
     * @param  array<string, string|null>  $row
     */
    abstract protected function role(array $row): string;

    /**
     * Columnas que el encabezado del CSV debe incluir, además del correo.
     *
     * @return list<string>
     */
    abstract protected function requiredColumns(): array;

    /** Comprueba que quien importa puede registrar cuentas en la carrera. */
    protected function authorizeCareer(Usuario $user, int $careerId): void {}

    public function columns(): array
    {
        $required = ['correo', ...$this->requiredColumns()];

        return array_combine(self::COLUMNS, array_map(fn (string $column): bool => in_array($column, $required, true), self::COLUMNS));
    }

    /**
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    protected static function exampleRow(array $values): array
    {
        return array_merge(array_fill_keys(self::COLUMNS, ''), $values);
    }

    /**
     * El rol de la columna, si viene, debe coincidir con el de la pantalla.
     *
     * @param  array<string, string|null>  $row
     */
    protected static function assertRole(array $row, string $role): string
    {
        if ($row['rol'] !== null && CsvReader::normalizeHeader($row['rol']) !== $role) {
            throw ValidationException::withMessages(['rol' => "En esta carga todas las cuentas son de rol {$role}."]);
        }

        return $role;
    }

    /** @return array<string, array<int, mixed>> */
    protected function extraRules(): array
    {
        return [];
    }

    public function import(array $row, Usuario $user): void
    {
        $role = $this->role($row);
        $careerId = null;
        $cycleId = null;
        if ($role !== 'administrador') {
            $careerId = $this->lookup->careerIdInFaculty($this->lookup->facultyId($row['facultad']), $row['carrera']);
            $this->authorizeCareer($user, $careerId);
        }
        if ($role === 'estudiante') {
            if ($row['ciclo'] === null) {
                throw ValidationException::withMessages(['ciclo' => 'El ciclo es obligatorio para los estudiantes.']);
            }
            // Acepta «6», «6.º», «6to»…: cuenta el número inicial.
            $cycleId = $this->lookup->cycleId((int) $careerId, preg_match('/^\d+/', $row['ciclo'], $number) ? $number[0] : $row['ciclo']);
        }

        $data = Validator::make([
            'correo' => $row['correo'] === null ? null : mb_strtolower($row['correo']),
            'cedula' => self::normalizeIdentification($row['cedula'] ?? null),
            'nombre' => self::fullName($row['apellidos'] ?? null, $row['nombres'] ?? null),
            'telefono' => self::normalizePhone($row['telefono'] ?? null),
        ], [
            'correo' => ['required', 'email:rfc', 'max:150', Rule::unique('usuario', 'correo'), ...($this->extraRules()['correo'] ?? [])],
            'cedula' => ['nullable', 'string', new CedulaOPasaporte, Rule::unique('usuario', 'cedula')],
            'nombre' => ['nullable', 'string', 'max:150', 'regex:/^[\pL\s]+$/u'],
            'telefono' => ['nullable', 'digits:10'],
        ], [
            'correo.unique' => 'El correo :input ya está registrado.',
            'cedula.unique' => 'La cédula o pasaporte :input ya está registrado.',
            'nombre.regex' => 'Los nombres y apellidos solo pueden contener letras y espacios.',
            'telefono.digits' => 'El teléfono debe tener exactamente 10 dígitos numéricos.',
            'correo.ends_with' => 'El correo debe ser institucional (@ueb.edu.ec).',
        ], ['correo' => 'correo', 'cedula' => 'cédula o pasaporte', 'nombre' => 'nombres y apellidos', 'telefono' => 'teléfono'])->validate();

        [$account, $provisionalPassword] = $this->createAccount->handle([
            'identification' => $data['cedula'],
            'name' => $data['nombre'] ?? CreateAccount::provisionalName($data['correo']),
            'email' => $data['correo'],
            'phone' => $data['telefono'],
            'role' => $role,
            'career_id' => $careerId,
            'must_complete_profile' => true,
        ]);
        if ($cycleId !== null) {
            $this->cycles->assign($account, Ciclo::query()->findOrFail($cycleId));
        }

        $account->notify(new QueuedProvisionalPasswordNotification($provisionalPassword));
    }

    /** El sistema guarda un solo campo con apellidos y nombres, en ese orden. */
    public static function fullName(?string $lastNames, ?string $firstNames): ?string
    {
        $name = trim(preg_replace('/\s+/u', ' ', trim(($lastNames ?? '').' '.($firstNames ?? ''))) ?? '');

        return $name === '' ? null : $name;
    }

    /**
     * Mayúsculas y sin espacios. Si Excel quitó el cero inicial de una cédula
     * (p. ej. 201234567 en lugar de 0201234567), se restituye.
     */
    public static function normalizeIdentification(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strtoupper((string) preg_replace('/\s+/', '', $value));
        if (preg_match('/^\d{9}$/', $value) && CedulaEcuatoriana::isValid('0'.$value)) {
            return '0'.$value;
        }

        return $value;
    }

    /** Restituye el cero inicial de un celular que Excel convirtió en número (991234567). */
    public static function normalizePhone(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string) preg_replace('/\D+/', '', $value);

        return preg_match('/^9\d{8}$/', $value) ? '0'.$value : $value;
    }
}
