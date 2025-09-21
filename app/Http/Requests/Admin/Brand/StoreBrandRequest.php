<?php

namespace App\Http\Requests\Admin\Brand;

use Illuminate\Foundation\Http\FormRequest;

class StoreBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
//        return $this->user()?->can('create', \App\Models\Brand::class) ?? true;
    }

    public function rules(): array
    {
        return [
            'name'              => ['required','string','max:120', 'unique:brands,name'],
            'slug'              => ['nullable','alpha_dash','max:160', 'unique:brands,slug'],
            'logo'              => ['nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],
            'meta_title'        => ['nullable','string','max:160'],
            'meta_description'  => ['nullable','string','max:255'],
            'description'       => ['nullable','string'],
            'top_brand'         => ['boolean'],
        ];
    }
}
