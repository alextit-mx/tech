<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use App\Support\BuilderSpecs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PendingSpecsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit-products') ?? false;
    }

    public function rules(): array
    {
        $slot = $this->route('product')->category?->builder_slot;
        $keys = Product::CRITICAL_SPECS[$slot] ?? [];
        if ($keys === []) {
            return ['specs' => ['prohibited']];
        }
        $rules = ['specs' => ['required', 'array:'.implode(',', $keys), 'min:1']];
        foreach (BuilderSpecs::fields($slot ?? '') as $key => $field) {
            if (! in_array($key, $keys, true)) {
                continue;
            }
            $rule = match ($field['type']) {
                'integer' => ['integer', 'min:0', 'max:1000000'],
                'tier' => ['integer', 'between:1,6'],
                'boolean' => ['boolean'],
                'list' => ['array', 'min:1', 'max:20'],
                default => ['string', 'max:100'],
            };
            if ($field['options']) {
                if ($field['type'] === 'list') {
                    $rules['specs.'.$key.'.*'] = ['required', 'string', 'distinct', Rule::in($field['options'])];
                } else {
                    $rule[] = Rule::in($field['options']);
                }
            }
            $rules['specs.'.$key] = ['sometimes', 'required', ...$rule];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['specs.array' => 'Envía solo campos críticos de este tipo de pieza.', '*.required' => 'Completa este campo.', '*.integer' => 'Escribe un número entero.', '*.min' => 'El valor es menor al permitido.', '*.max' => 'El valor excede el máximo permitido.', '*.between' => 'El nivel debe estar entre 1 y 6.', '*.boolean' => 'Selecciona sí o no.', '*.in' => 'Selecciona una opción válida.', '*.array' => 'Selecciona las opciones compatibles.', '*.string' => 'Escribe un texto válido.', '*.distinct' => 'No repitas las opciones.', 'specs.prohibited' => 'Esta categoría no tiene campos críticos por capturar.'];
    }
}
