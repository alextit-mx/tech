<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-settings') ?? false;
    }

    public function rules(): array
    {
        return ['exchange_rate' => 'required|numeric|min:0.01', 'quote_validity_days' => 'required|integer|min:1', 'business_name' => 'required|string', 'legal_name' => 'nullable|string', 'rfc' => 'nullable|string', 'address' => 'nullable|string', 'whatsapp' => 'nullable|string', 'email' => 'nullable|email', 'website' => 'nullable|string', 'bank_details' => 'nullable|string', 'warranty_terms' => 'nullable|string', 'quote_terms' => 'nullable|string'];
    }
}
