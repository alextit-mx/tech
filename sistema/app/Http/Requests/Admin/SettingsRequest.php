<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-settings') ?? false;
    }

    public function attributes(): array
    {
        return ['location_name' => 'nombre de la sucursal', 'phone' => 'teléfono'];
    }

    public function rules(): array
    {
        return ['exchange_rate' => 'required|numeric|min:0.01', 'quote_validity_days' => 'required|integer|min:1', 'business_name' => 'required|string', 'location_name' => 'nullable|string|max:120', 'phone' => 'nullable|string|max:40', 'legal_name' => 'nullable|string', 'rfc' => 'nullable|string', 'address' => 'nullable|string', 'whatsapp' => 'nullable|string', 'email' => 'nullable|email', 'website' => 'nullable|string', 'bank_details' => 'nullable|string', 'warranty_terms' => 'nullable|string', 'quote_terms' => 'nullable|string'];
    }
}
