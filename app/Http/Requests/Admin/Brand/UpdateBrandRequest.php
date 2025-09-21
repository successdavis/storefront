<?php

namespace App\Http\Requests\Admin\Brand;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
            return true;
//        return $this->user()?->can('update', $this->route('brand')) ?? true;
    }

    public function rules(): array
    {
        $brandId = $this->route('brand')->id;

        return [
            'name'              => ['required','string','max:120', Rule::unique('brands','name')->ignore($brandId)],
            'slug'              => ['nullable','alpha_dash','max:160', Rule::unique('brands','slug')->ignore($brandId)],
            'logo'              => ['nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],
            'meta_title'        => ['nullable','string','max:160'],
            'meta_description'  => ['nullable','string','max:255'],
            'description'       => ['nullable','string'],
            'top_brand'         => ['boolean'],
        ];
    }
}
