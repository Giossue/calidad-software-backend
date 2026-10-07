<?php

namespace App\Imports\Importers;

use App\Actions\Users\CreateAccount;
use App\Imports\CatalogLookup;
use App\Imports\Importer;
use App\Models\Usuario;
use App\Notifications\QueuedProvisionalPasswordNotification;
use App\Rules\CedulaEcuatoriana;
use App\Rules\CedulaOPasaporte;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Base de las importaciones de cuentas: el CSV solo exige el correo (y lo propio
 * de cada pantalla); los datos ausentes los completa el usuario al primer ingreso.
 */
abstract class AccountImporter implements Importer
{
    public function __construct(
        protected CatalogLookup $lookup,
        private CreateAccount $createAccount,
    ) {}

    /**
     * Rol que tendrá la cuenta creada a partir de la fila.
     *
     * @param  array<string, string|null>  $row
     */
    abstract protected function role(array $row): string;

    /**
     * Carrera de la cuenta, o null si el rol no la necesita.
     *
     * @param  array<string, string|null>  $row
     */
    abstract protected function careerId(array $row, Usuario $user, string $role): ?int;

    /** @return array<string, array<int, mixed>> */
    protected function extraRules(): array
    {
        return [];
    }

    public function import(array $row, Usuario $user): void
    {
        $role = $this->role($row);
        $careerId = $this->careerId($row, $user, $role);
        $data = Validator::make([
            'correo' => $row['correo'] === null ? null : mb_strtolower($row['correo']),
            'cedula' => self::normalizeIdentification($row['cedula'] ?? null),
            'nombre' => $row['nombre'] ?? null,
            'telefono' => self::normalizePhone($row['telefono'] ?? null),
        ], [
            'correo' => ['required', 'email:rfc', 'max:150', Rule::unique('usuario', 'correo'), ...($this->extraRules()['correo'] ?? [])],
            'cedula' => ['nullable', 'string', new CedulaOPasaporte, Rule::unique('usuario', 'cedula')],
            'nombre' => ['nullable', 'string', 'max:150', 'regex:/^[\pL\s]+$/u'],
            'telefono' => ['nullable', 'digits:10'],
        ], [
            'correo.unique' => 'El correo :input ya está registrado.',
            'cedula.unique' => 'La cédula o pasaporte :input ya está registrado.',
            'nombre.regex' => 'El nombre solo puede contener letras y espacios.',
            'telefono.digits' => 'El teléfono debe tener exactamente 10 dígitos numéricos.',
            'correo.ends_with' => 'El correo debe ser institucional (@ueb.edu.ec).',
        ], ['correo' => 'correo', 'cedula' => 'cédula o pasaporte', 'nombre' => 'nombre', 'telefono' => 'teléfono'])->validate();

        [$account, $provisionalPassword] = $this->createAccount->handle([
            'identification' => $data['cedula'],
            'name' => $data['nombre'] ?? CreateAccount::provisionalName($data['correo']),
            'email' => $data['correo'],
            'phone' => $data['telefono'],
            'role' => $role,
            'career_id' => $careerId,
            'must_complete_profile' => true,
        ]);

        $account->notify(new QueuedProvisionalPasswordNotification($provisionalPassword));
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
