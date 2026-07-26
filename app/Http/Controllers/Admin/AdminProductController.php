<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\{Admin\Product\ProductStoreRequest, Admin\Product\ProductUpdateRequest};
use App\Http\Resources\ProductResource;
use App\Models\{Brand, Category, Product, VariantType, Vendor};
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AdminProductController extends Controller
{
    public function index(Request $request, ProductService $productService)
    {
        $filters = $this->normalizeIndexFilters($request);

        $q = Product::query()
            ->with([
                'categories:id,name',
                'brand:id,name',
                'images:id,product_id,path,responsive_paths,alt,is_primary,sort_order',
                'variants' => fn ($query) => $query
                    ->active()
                    ->select([
                        'id',
                        'product_id',
                        'sku',
                        'quantity',
                        'reserved',
                        'regular_price',
                        'sale_starts_at',
                        'sale_ends_at',
                        'is_active',
                        'fulfillment_type',
                        'show_as_available_when_dropshipping',
                    ])
                    ->orderBy('regular_price')
                    ->orderBy('id'),
            ])
            ->withSum(['variants as total_stock' => fn ($query) => $query->where('is_active', true)], 'quantity')
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('name','like',"%{$search}%")
                        ->orWhere('slug','like',"%{$search}%");
                });
            })
            ->when($filters['status'] !== '', fn ($query) => $query
                ->where('is_active', $filters['status'] === 'published'))
            ->when($filters['featured'] !== '', fn ($query) => $query
                ->where('featured', $filters['featured'] === '1'))
            ->when($filters['stock'] === 'in', fn ($query) => $query
                ->whereHas('variants', fn ($variantQuery) => $variantQuery
                    ->where('is_active', true)
                    ->where('quantity', '>', 0)))
            ->when($filters['stock'] === 'out', fn ($query) => $query
                ->whereDoesntHave('variants', fn ($variantQuery) => $variantQuery
                    ->where('is_active', true)
                    ->where('quantity', '>', 0)))
            ->when($filters['fulfillment'] === 'dropshipping', fn ($query) => $query
                ->whereHas('variants', fn ($variantQuery) => $variantQuery
                    ->where('is_active', true)
                    ->where('fulfillment_type', \App\Models\ProductVariant::FULFILLMENT_DROPSHIPPING)))
            ->when($filters['fulfillment'] === 'stocked', fn ($query) => $query
                ->whereDoesntHave('variants', fn ($variantQuery) => $variantQuery
                    ->where('is_active', true)
                    ->where('fulfillment_type', \App\Models\ProductVariant::FULFILLMENT_DROPSHIPPING)))
            ->when($filters['brand_id'] !== null, fn ($query) => $query
                ->where('brand_id', $filters['brand_id']))
            ->when($filters['category_id'] !== null, fn ($query) => $query
                ->whereHas('categories', fn ($categoryQuery) => $categoryQuery
                    ->where('categories.id', $filters['category_id'])))
            ->orderByDesc('id');

        $toRow = function (Product $p) use ($productService): array {
            $card = $productService->toStorefrontCard($p, null, false);
            $onSale = $p->variants->contains(
                fn ($variant) => (bool) data_get(
                    $productService->resolveVariantPricing($variant, null, $p, false),
                    'has_discount',
                    false
                )
            );

            return [
                'id'          => $p->id,
                'name'        => $p->name,
                'slug'        => $p->slug,
                'thumb'       => $card['image'],
                'category'    => $p->categories->pluck('name')->filter()->implode(', '),
                'brand'       => optional($p->brand)->name,
                'total_stock' => (int) ($p->total_stock ?? 0),
                'published'   => (bool) $p->is_active,
                'featured'    => (bool) $p->featured,
                'on_sale'     => $onSale,
                'has_dropshipping' => $p->variants->contains(fn ($variant) => $variant->isDropshipping()),
                'updated_at'  => $p->updated_at->toDateTimeString(),
            ];
        };

        if ($filters['on_sale'] !== '') {
            // Sale status comes from dynamic discount rules, so it cannot be
            // filtered in SQL: map every match, filter, then paginate manually.
            $wantOnSale = $filters['on_sale'] === '1';
            $rows = $q->get()
                ->map($toRow)
                ->filter(fn (array $row) => $row['on_sale'] === $wantOnSale)
                ->values();

            $page = max(1, (int) $request->get('page', 1));
            $perPage = 20;
            $products = new \Illuminate\Pagination\LengthAwarePaginator(
                items: $rows->slice(($page - 1) * $perPage, $perPage)->values(),
                total: $rows->count(),
                perPage: $perPage,
                currentPage: $page,
                options: [
                    'path' => route('admin.products.index'),
                    'query' => array_filter($this->serializeIndexFilters($filters), fn ($value) => $value !== '' && $value !== null),
                ],
            );
        } else {
            $products = $q->paginate(20)->withQueryString()->through($toRow);
        }

        return Inertia::render('Admin/Products/Index', [
            'filters'  => $this->serializeIndexFilters($filters),
            'products' => $products,
            'brands'   => Brand::select('id', 'name')->orderBy('name')->get(),
            'categories' => Category::select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    protected function normalizeIndexFilters(Request $request): array
    {
        $enum = function (string $key, array $allowed) use ($request): string {
            $value = trim((string) $request->get($key, ''));

            return in_array($value, $allowed, true) ? $value : '';
        };

        $id = function (string $key) use ($request): ?int {
            $value = (int) $request->get($key, 0);

            return $value > 0 ? $value : null;
        };

        return [
            'search' => trim((string) $request->get('search', '')),
            'status' => $enum('status', ['published', 'draft']),
            'featured' => $enum('featured', ['1', '0']),
            'stock' => $enum('stock', ['in', 'out']),
            'on_sale' => $enum('on_sale', ['1', '0']),
            'fulfillment' => $enum('fulfillment', ['dropshipping', 'stocked']),
            'brand_id' => $id('brand_id'),
            'category_id' => $id('category_id'),
        ];
    }

    protected function serializeIndexFilters(array $filters): array
    {
        return [
            'search' => $filters['search'],
            'status' => $filters['status'],
            'featured' => $filters['featured'],
            'stock' => $filters['stock'],
            'on_sale' => $filters['on_sale'],
            'fulfillment' => $filters['fulfillment'],
            'brand_id' => $filters['brand_id'] !== null ? (string) $filters['brand_id'] : '',
            'category_id' => $filters['category_id'] !== null ? (string) $filters['category_id'] : '',
        ];
    }

    public function show(Product $product, ProductService $productService)
    {
        return Inertia::render('Admin/Products/Show', [
            'product' => $productService->adminDetailPayload($product),
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
            'suppliers'     => Vendor::select('id', 'name', 'active')->orderBy('name')->get(),
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

        $product->load([
            'images',
            'faqs',
            'variants' => fn ($query) => $query
                ->where('is_active', true)
                ->with(['values', 'images']),
        ]);
        return Inertia::render('Admin/Products/Edit', [
            'product' => new ProductResource($product),
            'categories'    => $categories,
            'brands'     => Brand::select('id','name')->orderBy('name')->get(),
            'suppliers'  => Vendor::select('id', 'name', 'active')->orderBy('name')->get(),
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
        return redirect()->route('admin.products.edit', $copy)->with('success', 'Product duplicated.');
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
