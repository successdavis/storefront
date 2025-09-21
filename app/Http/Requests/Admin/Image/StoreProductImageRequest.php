<?php

namespace App\Http\Requests\Admin\Image;


use Illuminate\Foundation\Http\FormRequest;


class StoreProductImageRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'images'                 => ['nullable', 'array'],
            'images.*.file'          => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp,avif,gif', 'max:5120'], // 5 MB
            'images.*.alt'           => ['nullable', 'string', 'max:255'],
            'images.*.is_primary'    => ['sometimes', 'boolean'],
            'images.*.sort_order'    => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
