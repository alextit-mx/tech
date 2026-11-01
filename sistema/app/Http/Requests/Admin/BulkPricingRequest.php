<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkPricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('bulk-pricing') ?? false;
    }

    public function rules(): array
    {
        return ['product_ids' => 'required_without:category_id|array', 'product_ids.*' => 'exists:products,id', 'category_id' => 'nullable|exists:categories,id', 'percent' => 'required|numeric|between:-90,500'];
    }
}
