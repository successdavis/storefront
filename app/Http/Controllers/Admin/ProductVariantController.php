<?php
namespace App\Http\Controllers\Admin;


use App\Http\Controllers\Controller;
use App\Models\{Product, ProductVariant, VariantValue};
use App\Http\Requests\Admin\Variant\{StoreVariantRequest, UpdateVariantRequest};
use Illuminate\Http\Request;


class ProductVariantController extends Controller
{
    public function index(Product $product)
    {
        $product->load(['variants' => fn ($query) => $query->active()->with('values.type')]);
        return response()->json($product->variants);
    }


    public function store(StoreVariantRequest $request, Product $product)
    {
        $created = [];
        foreach ($request->validated()['variants'] as $v) {
            $variant = $product->variants()->create([
                'sku' => $v['sku'],
                'quantity' => $v['quantity'] ?? 0,
                'regular_price' => $v['regular_price'],
            ]);
            $variant->values()->sync($v['value_ids']);
            $created[] = $variant->load('values.type');
        }
        return response()->json($created, 201);
    }


    public function update(UpdateVariantRequest $request, ProductVariant $variant)
    {
        $variant->update($request->validated());
        $variant->values()->sync($request->validated('value_ids'));
        return response()->json($variant->load('values.type'));
    }


    public function destroy(ProductVariant $variant)
    {
        $variant->update(['is_active' => false]);
        return response()->noContent();
    }
}
