<?php

namespace Tests\Feature\Notifications;

use App\Models\Usuario;
use App\Notifications\ProvisionalPasswordNotification;
use App\Support\ProvisionalPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProvisionalPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_generated_passwords_mix_every_character_group_without_ambiguous_characters(): void
    {
        foreach (range(1, 200) as $ignored) {
            $password = ProvisionalPassword::generate();

            $this->assertSame(16, strlen($password));
            $this->assertMatchesRegularExpression('/[A-Z]/', $password);
            $this->assertMatchesRegularExpression('/[a-z]/', $password);
            $this->assertMatchesRegularExpression('/\d/', $password);
            $this->assertMatchesRegularExpression('/[@#$%=?!]/', $password);
            $this->assertDoesNotMatchRegularExpression('/[Il1Oo0*_\\\\\[\]<>`]/', $password);
        }
    }

    public function test_the_email_shows_the_password_exactly_as_it_was_generated(): void
    {
        $user = Usuario::factory()->create();

        foreach (range(1, 50) as $ignored) {
            $password = ProvisionalPassword::generate();
            $html = (string) (new ProvisionalPasswordNotification($password))->toMail($user)->render();
            preg_match('/provisional de acceso es:(.*?)<\/p>/s', $html, $match);

            // Lo que ve el usuario en el correo, después de procesar el Markdown.
            $this->assertSame($password, trim(html_entity_decode(strip_tags($match[1] ?? ''))));
        }
    }
}
