<?php

namespace Tests\Feature\Api\V1\Auth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // La política completa solo rige en producción; la consulta de filtraciones no sale a la red.
        $this->app['env'] = 'production';
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
    }

    public function test_production_accepts_eight_characters_with_a_letter_a_number_and_a_symbol(): void
    {
        foreach (['clave12!', 'CLAVE12!', 'a1#bcdef', 'Nueva-Clave-2026'] as $password) {
            $this->assertTrue($this->passes($password), "Debería aceptar {$password}");
        }
    }

    public function test_production_rejects_passwords_missing_a_requirement(): void
    {
        foreach ([
            'clav12!' => 'menos de 8 caracteres',
            'clave123' => 'sin carácter especial',
            '1234567!' => 'sin letra',
            'clave!ab' => 'sin número',
        ] as $password => $reason) {
            $this->assertFalse($this->passes($password), "Debería rechazar {$password} ({$reason})");
        }
    }

    private function passes(string $password): bool
    {
        return Validator::make(['password' => $password], ['password' => [Password::default()]])->passes();
    }
}
