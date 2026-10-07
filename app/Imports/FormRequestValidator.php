<?php

namespace App\Imports;

use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Redirector;

/**
 * Valida una fila del CSV con el mismo Form Request del registro individual,
 * para que autorización, reglas y mensajes sean idénticos.
 */
class FormRequestValidator
{
    /**
     * @param  class-string<FormRequest>  $class
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function validate(string $class, array $data, Usuario $user): array
    {
        $request = $class::create('/', 'POST', $data);
        $request->setContainer(app())->setRedirector(app(Redirector::class));
        $request->setUserResolver(fn (): Usuario => $user);
        $request->validateResolved();

        return $request->validated();
    }
}
