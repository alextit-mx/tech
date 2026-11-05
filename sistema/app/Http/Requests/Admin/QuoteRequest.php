<?php

namespace App\Http\Requests\Admin;

use App\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuoteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'status' => ['required', Rule::in(array_keys(Quote::STATUSES))],
            'valid_until' => ['required', 'date'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'build_name' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.sku' => ['nullable', 'string', 'max:60'],
            'items.*.brand' => ['nullable', 'string', 'max:255'],
            'items.*.model' => ['nullable', 'string', 'max:255'],
            'items.*.warranty' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'customer_id' => 'cliente',
            'valid_until' => 'vigencia',
            'items' => 'partidas',
            'items.*.description' => 'descripción',
            'items.*.brand' => 'marca',
            'items.*.model' => 'modelo',
            'items.*.warranty' => 'garantía',
            'items.*.quantity' => 'cantidad',
            'items.*.unit_price' => 'precio',
        ];
    }
}
