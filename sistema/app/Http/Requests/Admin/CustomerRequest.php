<?php

namespace App\Http\Requests\Admin;

use App\Models\Customer;
use App\Support\Sat;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && in_array($this->user()?->role, ['owner', 'seller'], true);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'kind' => $this->input('kind') ?: 'mostrador',
            'phone' => Customer::normalizePhone($this->input('phone')),
            'rfc' => $this->filled('rfc') && is_string($this->input('rfc')) ? mb_strtoupper(trim($this->input('rfc'))) : null,
        ]);
    }

    public function rules(): array
    {
        $registered = $this->input('kind') === 'registrado';
        $required = Rule::requiredIf($registered);
        $rfcRules = $registered ? [
            $required, 'nullable', 'string', 'max:13',
            function (string $attribute, mixed $value, Closure $fail) {
                if (! Sat::validRfc($value)) {
                    $fail('El RFC debe tener un formato válido de 12 o 13 caracteres.');
                }
            },
            Rule::unique('customers', 'rfc')->where('kind', 'registrado')->ignore($this->route('customer')),
        ] : ['nullable', 'string', 'between:12,13'];

        return [
            'kind' => ['required', Rule::in(['mostrador', 'registrado'])],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'digits:10', Rule::unique('customers', 'phone')->ignore($this->route('customer'))],
            'email' => ['nullable', 'email', 'max:120'],
            'company' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'rfc' => $rfcRules,
            'legal_name' => [$required, 'nullable', 'string', 'max:255'],
            'tax_regime' => [$required, 'nullable', Rule::in(array_keys(Sat::REGIMES)), function (string $attribute, mixed $value, Closure $fail) use ($registered) {
                $person = Sat::personType((string) $this->input('rfc'));
                if ($registered && $person && is_scalar($value) && isset(Sat::REGIMES[$value]) && ! in_array($person, Sat::REGIMES[$value]['persons'], true)) {
                    $fail('El régimen fiscal no corresponde al tipo de persona del RFC.');
                }
            }],
            'cfdi_use' => ['nullable', Rule::in(array_keys(Sat::CFDI_USES))],
            'tax_zip' => [$required, 'nullable', 'digits:5'],
            'street' => ['nullable', 'string', 'max:255'],
            'ext_number' => ['nullable', 'string', 'max:255'],
            'int_number' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', Rule::in(Sat::STATES)],
            'zip' => ['nullable', 'digits:5'],
            'address_notes' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['kind' => 'tipo de cliente', 'name' => 'nombre del contacto', 'phone' => 'WhatsApp', 'email' => 'correo',
            'company' => 'empresa', 'position' => 'puesto', 'rfc' => 'RFC', 'legal_name' => 'razón social',
            'tax_regime' => 'régimen fiscal', 'cfdi_use' => 'uso de CFDI', 'tax_zip' => 'CP fiscal', 'street' => 'calle',
            'ext_number' => 'número exterior', 'int_number' => 'número interior', 'neighborhood' => 'colonia',
            'city' => 'municipio o alcaldía', 'state' => 'estado', 'zip' => 'código postal', 'address_notes' => 'referencias', 'notes' => 'notas'];
    }
}
