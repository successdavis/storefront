<?php

namespace App\Http\Requests\Admin\Product;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates wizard auto-save payloads: product-level fields only.
 * Variants, images and publish state are handled at finalize time.
 */
class ProductDraftRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'category_ids'      => ['required', 'array'],
            'category_ids.*'    => ['exists:categories,id'],
            'brand_id'          => ['required', 'exists:brands,id'],
            'name'              => ['required', 'string', 'max:255'],
            'meta_title'        => ['nullable', 'string', 'max:255'],
            'meta_description'  => ['nullable', 'string', 'max:255'],
            'youtube_video_url' => ['nullable', 'url'],
            'cash_on_delivery'  => ['nullable', 'boolean'],
            'featured'          => ['nullable', 'boolean'],
            'weight'            => ['nullable', 'numeric'],
            'weight_unit'       => ['nullable', 'in:g,kg,lb,oz'],
            'description'       => ['required', 'string'],
            'length'            => ['nullable', 'numeric'],
            'width'             => ['nullable', 'numeric'],
            'height'            => ['nullable', 'numeric'],

            'faqs'              => ['array'],
            'faqs.*.question'   => ['required', 'string', 'max:255'],
            'faqs.*.answer'     => ['required', 'string'],
            'faqs.*.is_active'  => ['boolean'],
            'faqs.*.position'   => ['integer'],
        ];
    }
}
