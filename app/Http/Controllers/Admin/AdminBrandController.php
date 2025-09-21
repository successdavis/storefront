<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Brand\StoreBrandRequest;
use App\Http\Requests\Admin\Brand\UpdateBrandRequest;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminBrandController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q'));

        $brands = Brand::query()
            ->when($search !== '', fn ($q) =>
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
            )
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        return Inertia::render('Admin/Brands/Index', [
            'brands' => $brands,
            'search' => $search,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Brands/Upsert');
    }

    public function store(StoreBrandRequest $request)
    {
        $data = $request->validated();

        // Generate slug if missing
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);

        // Ensure unique slug
        $data['slug'] = $this->uniqueSlug($data['slug']);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('brands', 'public');
        }

        Brand::create($data);

//        return response()->noContent();
        return redirect()->route('admin.brands.index')
            ->with('success', 'Brand created successfully.');
    }

    public function edit(Brand $brand): Response
    {
        // Include computed logo_url via appends
        return Inertia::render('Admin/Brands/Upsert', [
            'brand' => $brand->only([
                'id','name','slug','meta_title','meta_description','description','top_brand','logo'
            ]) + ['logo_url' => $brand->logo_url],
        ]);
    }

    public function update(UpdateBrandRequest $request, Brand $brand)
    {
        $data = $request->validated();

        // Slug
        if (!empty($data['slug'])) {
            $data['slug'] = $this->uniqueSlug($data['slug'], $brand->id);
        } else {
            // Keep existing slug or regenerate from name if none
            $data['slug'] = $brand->slug ?: $this->uniqueSlug(Str::slug($data['name']), $brand->id);
        }

        // Logo
        if ($request->hasFile('logo')) {
            $newPath = $request->file('logo')->store('brands', 'public');
            // delete old if exists
            if ($brand->logo && Storage::disk('public')->exists($brand->logo)) {
                Storage::disk('public')->delete($brand->logo);
            }
            $data['logo'] = $newPath;
        }

        $brand->update($data);

        return redirect()->route('admin.brands.index')
            ->with('success', 'Brand updated successfully.');
    }

    public function destroy(Brand $brand)
    {
        if ($brand->logo && Storage::disk('public')->exists($brand->logo)) {
            Storage::disk('public')->delete($brand->logo);
        }

        $brand->delete();

        return redirect()->route('admin.brands.index')
            ->with('success', 'Brand deleted.');
    }

    // Optional: simple toggle endpoint if you want a quick switch from the list
    public function toggleTop(Brand $brand)
    {
        $brand->update(['top_brand' => ! $brand->top_brand]);

        return back()->with('success', 'Top brand status updated.');
    }

    private function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = Str::lower($base) ?: 'brand';
        $original = $slug;
        $i = 1;

        while (
            Brand::where('slug', $slug)
                ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$original}-{$i}";
            $i++;
        }

        return $slug;
    }
}
