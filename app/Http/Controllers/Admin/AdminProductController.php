<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\{Admin\Product\ProductStoreRequest, Admin\Product\ProductUpdateRequest};
use App\Http\Resources\ProductResource;
use App\Models\{Brand, Category, Product, VariantType};
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AdminProductController extends Controller
{
    public function index(Request $request)
    {
        $q = Product::query()
            ->with(['categories:id,name', 'brand:id,name'])
            ->withSum('variants as total_stock', 'quantity')
            ->when($request->get('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name','like',"%{$search}%")
                        ->orWhere('slug','like',"%{$search}%");
                });
            })
            ->orderByDesc('id');

        $products = $q->paginate(20)->withQueryString();

        return Inertia::render('Admin/Products/Index', [
            'filters'  => $request->only('search'),
            'products' => $products->through(function ($p) {
                return [
                    'id'          => $p->id,
                    'name'        => $p->name,
                    'slug'        => $p->slug,
                    'thumb'       => optional($p->images->first())->path, // optional thumbnail
                    'category'    => optional($p->category)->name,
                    'brand'       => optional($p->brand)->name,
                    'total_stock' => (int) ($p->total_stock ?? 0),
                    'published'   => (bool) $p->is_active,
                    'featured'    => (bool) $p->featured,
                    'updated_at'  => $p->updated_at->toDateTimeString(),
                ];
            }),
            // base storefront URL used by the View button. Adjust to your route.
            'storefront_base' => url('/products'),
        ]);
    }

    public function create()
    {
        // fetch roots with full descendant tree
        $roots = Category::whereNull('parent_id')
            ->select('id', 'name', 'parent_id')
            ->with(['childrenRecursive' => function ($q) {
                $q->select('id', 'name', 'parent_id')->orderBy('name');
            }])
            ->orderBy('name')
            ->get();

        // reshape to { id, name, children: [...] }
        $categories = $roots->map(fn ($root) => $this->catToArray($root))->values();

        return Inertia::render('Admin/Products/Edit', [
            'product'       => null,
            'categories'    => $categories,
            'brands'        => Brand::select('id', 'name')->orderBy('name')->get(),
            'variantTypes'  => VariantType::with('values:id,variant_type_id,value')
                ->select('id', 'name')->get(),
        ]);
    }

    public function edit(Product $product)
    {
        // fetch roots with full descendant tree
        $roots = Category::whereNull('parent_id')
            ->select('id', 'name', 'parent_id')
            ->with(['childrenRecursive' => function ($q) {
                $q->select('id', 'name', 'parent_id')->orderBy('name');
            }])
            ->orderBy('name')
            ->get();

        // reshape to { id, name, children: [...] }
        $categories = $roots->map(fn ($root) => $this->catToArray($root))->values();

        $product->load(['images','faqs','variants.values','variants.images']);
        return Inertia::render('Admin/Products/Edit', [
            'product' => new ProductResource($product),
            'categories'    => $categories,
            'brands'     => Brand::select('id','name')->orderBy('name')->get(),
            'variantTypes' => VariantType::with('values:id,variant_type_id,value')
                ->select('id','name')->get(),
        ]);
    }

    public function store(ProductStoreRequest $request, ProductService $svc)
    {
        $product = $svc->create($request->validated());
        return redirect()->route('admin.products.edit', $product)->with('success', 'Product created.');
    }

    public function update(ProductUpdateRequest $request, Product $product, ProductService $svc)
    {
        $svc->update($product, $request->validated());
        return back()->with('success', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        // Check if the product has related orders through its variants
        $hasOrders = $product->variants()
            ->whereHas('orderItems') // assumes ProductVariant has relation ->orderItems()
            ->exists();

        if ($hasOrders) {
            return redirect()
                ->route('admin.products.index')
                ->with('error', 'This product cannot be deleted because it has existing orders.');
        }

        // Soft delete (safe)
        $product->delete();

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Product deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $this->validateIds($request);

        // Check if any of the selected products have orders
        $hasOrders = Product::whereIn('id', $ids)
            ->whereHas('variants.orderItems')
            ->exists();

        if ($hasOrders) {
            return redirect()->back()->with('error', 'Some products cannot be deleted because they have existing orders.');
        }

        // Safe to soft delete
        Product::whereIn('id', $ids)->delete();

        return redirect()->back()->with('error', 'Selected products deleted successfully.');
    }

    public function togglePublished(Product $product)
    {
        $product->update(['is_active' => ! $product->is_active]);
        return back()->with('success', 'Publish state updated.');
    }

    // PATCH /admin/products/bulk/publish
    public function bulkPublish(Request $request)
    {
        $ids = $this->validateIds($request);

        Product::whereIn('id', $ids)->update(['is_active' => 1]);

        return back()->with('success', 'Publish state updated.');
    }

    // PATCH /admin/products/bulk/unpublish
    public function bulkUnpublish(Request $request)
    {
        $ids = $this->validateIds($request);

        Product::whereIn('id', $ids)->update(['is_active' => 0]);

        return back()->with('success', 'Publish state updated.');
    }


    public function toggleFeatured(Product $product)
    {
        $product->update(['featured' => ! $product->featured]);
        return back()->with('success', 'Featured state updated.');
    }

// deep duplicate
    public function duplicate(Product $product, \App\Services\ProductService $svc)
    {
        $copy = $svc->duplicate($product);
        return redirect()->route('products.edit', $copy)->with('success', 'Product duplicated.');
    }

    protected function validateIds(Request $request): array
    {
        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:products,id'],
        ]);

        return collect($data['ids'])
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();
    }

    private function catToArray(Category $cat): array
    {
        return [
            'id'        => $cat->id,
            'name'      => $cat->name,
            'children'  => collect($cat->childrenRecursive ?? [])
                ->map(fn ($c) => $this->catToArray($c))
                ->values()
                ->all(),
        ];
    }
}
