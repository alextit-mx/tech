<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($product?->id)],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($product?->id)],
            'category_id' => ['required', 'exists:categories,id'], 'brand_id' => ['nullable', 'exists:brands,id'],
            'short_description' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string'],
            'cost' => ['nullable', 'numeric', 'min:0'], 'cost_currency' => ['required', 'in:MXN,USD'],
            'price' => ['nullable', 'numeric', 'min:0'], 'price_mode' => ['required', 'in:show,quote'],
            'stock' => ['required', 'integer', 'min:0'], 'is_published' => ['sometimes', 'boolean'], 'is_featured' => ['sometimes', 'boolean'],
            'specs' => ['nullable', 'array'], 'warranty_months' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
