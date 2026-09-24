<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Role;
use App\Models\Usuario;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): Usuario
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = Usuario::create([
            'cedula' => $input['identification'],
            'nombre' => $input['name'],
            'correo' => $input['email'],
            'password_hash' => $input['password'],
        ]);

        $user->roles()->attach(Role::query()->where('slug', 'estudiante')->value('id'));

        return $user;
    }
}
