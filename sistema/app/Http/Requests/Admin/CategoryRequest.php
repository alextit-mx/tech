<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-categories') ?? false;
    }

    public function rules(): array
    {
        return ['name' => 'required|string|max:255', 'slug' => 'nullable|string|max:255', 'parent_id' => 'nullable|exists:categories,id', 'description' => 'nullable|string', 'builder_slot' => 'nullable|in:cpu,motherboard,ram,gpu,storage,psu,cooler,case', 'sort' => 'sometimes|integer|min:0', 'is_visible' => 'sometimes|boolean'];
    }
}
