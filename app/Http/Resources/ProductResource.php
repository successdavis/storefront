<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,

            // many-to-many categories
            // always expose the IDs; include light objects only if loaded to avoid N+1
            'category_ids'       => $this->when(
                true,
                fn () => $this->categories?->pluck('id')->map(fn ($id) => (int) $id)->values() ?? collect()
            ),
            'categories'         => $this->when(
                $this->resource->relationLoaded('categories'),
                fn () => $this->categories->map(fn ($c) => [
                    'id'        => (int) $c->id,
                    'name'      => $c->name,
                    'slug'      => $c->slug,
                    'parent_id' => $c->parent_id,
                ])->values()
            ),

            'brand_id'           => $this->brand_id,
            'name'               => $this->name,
            'slug'               => $this->slug,
            'meta_title'         => $this->meta_title,
            'meta_description'   => $this->meta_description,
            'youtube_video_url'  => $this->youtube_video_url,
            'cash_on_delivery'   => (bool) $this->cash_on_delivery,
            'featured'           => (bool) $this->featured,
            'weight'             => $this->weight,
            'weight_unit'        => $this->weight_unit,
            'description'        => $this->description,
            'is_active'          => (bool) $this->is_active,
            'length'             => $this->length,
            'width'              => $this->width,
            'height'             => $this->height,

            'images' => $this->images->map(fn ($img) => [
                'id'         => (int) $img->id,
                'path'       => $img->path,
                'alt'        => $img->alt,
                'is_primary' => (bool) $img->is_primary,
                'sort_order' => (int) $img->sort_order,
            ])->values(),

            'faqs' => $this->faqs->map(fn ($faq) => [
                'id'                 => (int) $faq->id,
                'product_variant_id' => $faq->product_variant_id ? (int) $faq->product_variant_id : null,
                'question'           => $faq->question,
                'answer'             => $faq->answer,
                'is_active'          => (bool) $faq->is_active,
                'position'           => (int) $faq->position,
                'slug'               => $faq->slug,
                'locale'             => $faq->locale,
            ])->values(),

            'variants' => $this->variants->load('values', 'images')->map(function ($v) {
                return [
                    'id'                        => (int) $v->id,
                    'is_active'                 => (bool) $v->is_active,
                    'has_history'               => $v->hasDurableHistory(),
                    'sku'                       => $v->sku,
                    'quantity'                  => (int) $v->quantity,
                    'barcode'                   => $v->barcode,
                    'last_purchase_price'       => $v->last_purchase_price,
                    'regular_price'             => $v->regular_price,
                    'sale_starts_at'            => optional($v->sale_starts_at)?->toIso8601String(),
                    'sale_ends_at'              => optional($v->sale_ends_at)?->toIso8601String(),
                    'weight'                    => $v->weight,
                    'length'                    => $v->length,
                    'width'                     => $v->width,
                    'height'                    => $v->height,
                    'fulfillment_type'          => $v->fulfillment_type ?? 'stocked',
                    'is_dropshippable'          => (bool) $v->is_dropshippable,
                    'default_supplier_id'       => $v->default_supplier_id ? (int) $v->default_supplier_id : null,
                    'supplier_cost'             => $v->supplier_cost,
                    'supplier_lead_time_days'   => $v->supplier_lead_time_days,
                    'show_as_available_when_dropshipping' => (bool) $v->show_as_available_when_dropshipping,
                    'dropshipping_note'         => $v->dropshipping_note,
                    'value_ids'                 => $v->values->pluck('id')->map(fn ($id) => (int) $id)->values(),
                    'images'                    => $v->images->map(fn ($img) => [
                        'id'                    => (int) $img->id,
                        'path'                  => $img->path,
                        'alt'                   => $img->alt,
                        'is_primary'            => (bool) $img->is_primary,
                        'sort_order'            => (int) $img->sort_order,
                    ])->values(),
                ];
            })->values(),
        ];
    }
}
