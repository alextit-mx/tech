<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class LogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-settings') ?? false;
    }

    public function rules(): array
    {
        return ['image' => ['bail', 'required', 'file', 'max:2048', 'image', 'mimes:png,jpg,jpeg,webp', $this->route('type') === 'icon' ? 'dimensions:min_width=256,min_height=256' : 'dimensions:min_width=300']];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'Elige una imagen para subir.',
            'image.file' => 'El archivo debe ser una imagen PNG, JPG o WebP.',
            'image.max' => 'La imagen puede pesar hasta 2 MB.',
            'image.image' => 'Solo se aceptan imágenes PNG, JPG o WebP. No se permiten archivos SVG.',
            'image.mimes' => 'Solo se aceptan imágenes PNG, JPG o WebP. No se permiten archivos SVG.',
            'image.dimensions' => $this->route('type') === 'icon' ? 'El ícono debe medir al menos 256 × 256 px.' : 'El logo debe medir al menos 300 px de ancho.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->route('type') !== 'icon' || $validator->errors()->has('image')) {
                return;
            }
            [$width, $height] = getimagesize($this->file('image')->getRealPath());
            if (abs($width - $height) / min($width, $height) > 0.02) {
                $validator->errors()->add('image', 'El ícono debe ser cuadrado (se permite una diferencia de hasta 2 %).');
            }
        }];
    }
}
