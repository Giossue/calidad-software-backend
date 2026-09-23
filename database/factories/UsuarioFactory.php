<?php

namespace Database\Factories;

use App\Models\Usuario;
use App\Rules\CedulaEcuatoriana;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cedula' => self::generateValidCedula(),
            'nombre' => fake()->name(),
            'correo' => fake()->unique()->safeEmail(),
            'telefono' => fake()->unique()->numerify('09########'),
            'email_verified_at' => now(),
            'password_hash' => static::$password ??= Hash::make('password'),
            'rol' => 'estudiante',
            'estado' => true,
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Generates a cédula that satisfies the Ecuadorian módulo-10 checksum,
     * matching the constraints enforced by App\Rules\CedulaEcuatoriana.
     */
    private static function generateValidCedula(): string
    {
        $province = str_pad((string) fake()->numberBetween(1, 24), 2, '0', STR_PAD_LEFT);
        $thirdDigit = (string) fake()->numberBetween(0, 5);
        $rest = fake()->unique()->numerify('######');

        $firstNineDigits = array_map('intval', str_split($province.$thirdDigit.$rest));

        return implode('', $firstNineDigits).CedulaEcuatoriana::checkDigit($firstNineDigits);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
