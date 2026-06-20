<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AdminCategoryController extends Controller
{
    public function index(Request $request)
    {
        // Optional search by name
        $search = trim((string) $request->query('q'));

        $categories = Category::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            })
            ->whereNull('parent_id')
            ->with([
                'children' => function ($q) {
                    $q->orderBy('name')
                        ->withCount('products');
                },
            ])
            ->withCount('products', 'children')
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories,
            'search'     => $search,
        ]);
    }

    /**
     * Display a single category with its subcategories and products.
     *
     * Assumes the Product model defines:
     *  - variants(): hasMany(ProductVariant::class)
     * Assumes the Category model defines:
     *  - children(): hasMany(Category::class, 'parent_id')
     *  - parent(): belongsTo(Category::class, 'parent_id')
     *  - products(): hasMany(Product::class)
     */
    public function show(Request $request, Category $category)
    {
        // Preload basic tree info
        $category->load([
            'parent',
            'children' => function ($q) {
                $q->orderBy('name')->withCount('products');
            },
        ]);

        // Sorting and basic filters
        $sort = $request->query('sort'); // price_asc | price_desc | newest | name
        $onlyActive = $request->boolean('active', true);

        $productsQuery = Product::query()
            ->where('category_id', $category->id)
            ->when($onlyActive, fn ($q) => $q->where('is_active', true))
            // Price and stock aggregates from variants
            ->withMin('variants as min_price', 'price')
            ->withSum('variants as total_stock', 'quantity');

        // Apply sorting
        switch ($sort) {
            case 'price_asc':
                $productsQuery->orderBy('min_price')->orderBy('name');
                break;
            case 'price_desc':
                $productsQuery->orderByDesc('min_price')->orderBy('name');
                break;
            case 'newest':
                $productsQuery->latest();
                break;
            case 'name':
                $productsQuery->orderBy('name');
                break;
            default:
                // Default: available first, then name
                $productsQuery->orderByDesc('total_stock')->orderBy('name');
                break;
        }

        $products = $productsQuery
            ->with(['brand'])   // if you show brand chips on cards
            ->paginate(24)
            ->withQueryString();

        // Simple breadcrumb data
        $breadcrumbs = $this->buildBreadcrumbs($category);

        return view('categories.show', [
            'category'    => $category,
            'breadcrumbs' => $breadcrumbs,
            'products'    => $products,
            'sort'        => $sort,
            'onlyActive'  => $onlyActive,
        ]);
    }

    /**
     * Build a parent-to-child breadcrumb trail.
     *
     * @return array<int, array{name:string, url:string|null}>
     */
    protected function buildBreadcrumbs(Category $category): array
    {
        $trail = [];
        $current = $category;

        while ($current) {
            $trail[] = [
                'name' => $current->name,
                'url'  => $current->is($category) ? null : route('categories.show', $current),
            ];
            $current = $current->parent;
        }

        return array_reverse($trail);
    }

    public function create()
    {
        $parents = \App\Models\Category::orderBy('name')->get(['id','name']);
        return Inertia::render('Admin/Categories/Create', ['parents' => $parents]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'             => ['required','string','max:255'],
            'parent_id'        => ['nullable','exists:categories,id'],
            'order'            => ['nullable','integer','min:0'],
            'featured'         => ['boolean'],
            'meta_title'       => ['nullable','string','max:255'],
            'meta_description' => ['nullable','string','max:255'],
            'slug'             => ['nullable','string','max:255','unique:categories,slug'],
            'description'      => ['nullable','string'],
            'banner'           => ['nullable','image','max:2048'],
            'icon'             => ['nullable','image','max:1024'],
            'cover_image'      => ['nullable','image','max:4096'],
        ]);

        // Handle files (store in storage/app/public/categories)
        foreach (['banner','icon','cover_image'] as $key) {
            if ($request->hasFile($key)) {
                $data[$key] = $request->file($key)->store('categories', 'public');
            }
        }

        // Fallback slug
        if (empty($data['slug'])) {
            $data['slug'] = \Str::slug($data['name']);
        }

        \App\Models\Category::create($data);

        return redirect()->route('admin.categories.index')->with('success', 'Category created.');
    }

    public function edit(Category $category)
    {
        $parents = Category::where('id', '!=', $category->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Admin/Categories/Create', [
            'category' => $category,
            'parents'  => $parents,
            'mode'     => 'edit', // flag to switch button text & route
        ]);
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'parent_id'        => ['nullable', 'exists:categories,id'],
            'order'            => ['nullable', 'integer', 'min:0'],
            'featured'         => ['boolean'],
            'meta_title'       => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:255'],
            'slug'             => ['nullable', 'string', 'max:255', 'unique:categories,slug,' . $category->id],
            'description'      => ['nullable', 'string'],
            'banner'           => ['nullable', 'image', 'max:2048'],
            'icon'             => ['nullable', 'image', 'max:1024'],
            'cover_image'      => ['nullable', 'image', 'max:4096'],
        ]);

        foreach (['banner', 'icon', 'cover_image'] as $key) {
            if ($request->hasFile($key)) {
                $data[$key] = $request->file($key)->store('categories', 'public');
            }
        }

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category->update($data);

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Category updated.');
    }

    public function removeParent(Category $category)
    {
        if ($category->parent_id === null) {
            return back()->with('info', 'Category is already a parent category.');
        }

        $category->update(['parent_id' => null]);

        return back()->with('success', 'Category removed from parent category.');
    }

    public function destroy(Category $category)
    {
        // Optional: prevent deleting a parent if it has children
        if ($category->children()->count() > 0) {
            return redirect()
                ->route('admin.categories.index')
                ->with('error', 'You cannot delete a category that has sub-categories.');
        }

        // Optional: delete related files if you store banners/icons/covers
        foreach (['banner', 'icon', 'cover_image'] as $field) {
            if ($category->$field && \Storage::disk('public')->exists($category->$field)) {
                \Storage::disk('public')->delete($category->$field);
            }
        }

        $category->delete();

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}
