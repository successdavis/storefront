<?php

namespace App\Http\Requests\Admin\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\ProductVariant;

class ProductStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
//        dd(request()->all());
        return [
            'category_ids'   => ['required','array'],
            'category_ids.*' => ['exists:categories,id'],
            'brand_id'    => ['required','exists:brands,id'],
            'name'        => ['required','string','max:255'],
            'slug'        => ['nullable','string','max:255','unique:products,slug'],
            'meta_title'  => ['nullable','string','max:255'],
            'meta_description' => ['nullable','string','max:255'],
            'youtube_video_url' => ['nullable','url'],
            'cash_on_delivery'  => ['nullable','boolean'],
            'featured'          => ['nullable','boolean'],
            'weight'            => ['nullable','numeric'],
            'weight_unit'       => ['nullable','in:g,kg,lb,oz'],
            'description'       => ['required','string'],
            'is_active'         => ['boolean'],
            'length'            => ['nullable','numeric'],
            'width'             => ['nullable','numeric'],
            'height'            => ['nullable','numeric'],

            'faqs' => ['array'],
            'faqs.*.question' => ['required','string','max:255'],
            'faqs.*.answer'   => ['required','string'],
            'faqs.*.is_active'=> ['boolean'],
            'faqs.*.position' => ['integer'],

            'variants' => ['array'],
            'variants.*.sku'                    => ['nullable','string','max:64','unique:product_variants,sku'],
            'variants.*.archived'               => ['nullable','boolean'],
            'variants.*.quantity'               => ['integer','min:0'],
            'variants.*.barcode'                => ['nullable','string','max:64','unique:product_variants,barcode'],
            'variants.*.last_purchase_price'    => ['required','numeric','min:0'],
            'variants.*.regular_price'             => ['required','numeric'],
            'variants.*.sale_starts_at'         => ['nullable','date'],
            'variants.*.sale_ends_at'           => ['nullable','date','after_or_equal:variants.*.sale_starts_at'],
            'variants.*.weight'                 => ['nullable','numeric','min:0'],
            'variants.*.length'                 => ['nullable','numeric','min:0'],
            'variants.*.width'                  => ['nullable','numeric','min:0'],
            'variants.*.height'                 => ['nullable','numeric','min:0'],
            'variants.*.replenishment_status'   => ['nullable', Rule::in([
                ProductVariant::REPLENISHMENT_REORDERABLE,
                ProductVariant::REPLENISHMENT_PAUSED,
                ProductVariant::REPLENISHMENT_DISCONTINUED,
            ])],
            'variants.*.replenishment_note'     => ['nullable','string','max:1000'],
            'variants.*.fulfillment_type'       => ['nullable', Rule::in(['stocked', 'dropshipping'])],
            'variants.*.default_supplier_id'    => ['nullable','integer','exists:vendors,id'],
            'variants.*.supplier_cost'          => ['nullable','numeric','min:0'],
            'variants.*.supplier_lead_time_days'=> ['nullable','integer','min:0'],
            'variants.*.show_as_available_when_dropshipping' => ['nullable','boolean'],
            'variants.*.dropshipping_note'      => ['nullable','string'],
            'variants.*.value_ids'              => ['array'], // variant_values ids
            'variants.*.images'                 => ['array'],
            'variants.*.images.*.path'          => ['required','string'],
        ];
    }
}
