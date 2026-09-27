<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Usuario;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignCoordinatorCareersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('administrador') && $this->user()->estado;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $target = $this->route('user');
        $existingIds = $target instanceof Usuario ? $target->coordinatedCareers()->pluck('carrera.id_carrera')->all() : [];

        return [
            'career_ids' => ['present', 'array'],
            'career_ids.*' => ['required', 'integer', 'distinct', Rule::exists('carrera', 'id_carrera')->where(
                fn (Builder $query) => $query->where('estado', true)->orWhereIn('id_carrera', $existingIds),
            )],
        ];
    }
}
