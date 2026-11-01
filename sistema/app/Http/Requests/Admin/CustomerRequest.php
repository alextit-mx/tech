<?php

namespace App\Http\Requests\Admin;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => Customer::normalizePhone($this->input('phone')), 'rfc' => $this->filled('rfc') ? strtoupper($this->input('rfc')) : null]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'digits:10', Rule::unique('customers', 'phone')->ignore($this->route('customer'))],
            'email' => ['nullable', 'email', 'max:120'],
            'rfc' => ['nullable', 'string', 'between:12,13'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nombre', 'phone' => 'WhatsApp', 'email' => 'correo'];
    }
}
