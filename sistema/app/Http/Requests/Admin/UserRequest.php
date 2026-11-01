<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') ?? false;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return ['name' => 'required|string|max:255', 'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user?->id)], 'role' => 'required|in:owner,seller', 'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'], 'is_active' => 'required|boolean'];
    }
}
