<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $suppliers = Vendor::query()
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->withCount(['dropshipVariants', 'dropshipFulfillments'])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Suppliers/Index', [
            'filters' => $filters,
            'suppliers' => $suppliers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Vendor::query()->create($this->validated($request));

        return back()->with('success', 'Supplier created.');
    }

    public function update(Request $request, Vendor $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request));

        return back()->with('success', 'Supplier updated.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
        ]);
    }
}
