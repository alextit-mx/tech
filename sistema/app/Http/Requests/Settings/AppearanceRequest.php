<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppearanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['appearance' => ['required', Rule::in(['light', 'dark', 'system'])]];
    }

    public function messages(): array
    {
        return ['appearance.required' => 'Elige una apariencia.', 'appearance.in' => 'Elige claro, oscuro o sistema.'];
    }
}
