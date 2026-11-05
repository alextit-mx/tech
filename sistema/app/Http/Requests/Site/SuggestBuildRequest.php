<?php

namespace App\Http\Requests\Site;

use App\Models\Build;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SuggestBuildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['uso' => ['required', 'string', Rule::in(array_keys(Build::USES))], 'presupuesto' => ['required', 'string', Rule::in(['esencial', 'equilibrada', 'alta', 'extrema'])]];
    }

    public function messages(): array
    {
        return ['uso.required' => 'Elige para qué usarás tu PC.', 'uso.in' => 'Elige un uso válido.', 'presupuesto.required' => 'Elige el presupuesto.', 'presupuesto.in' => 'Elige un presupuesto válido.'];
    }
}
