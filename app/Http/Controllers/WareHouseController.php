<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Requests\UpdateWarehouseRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WareHouseController extends Controller
{
    /**
     * Display list with search + pagination.
     */
    public function index(Request $request)
    {
        $warehouses = Warehouse::query()
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('city', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->through(fn ($warehouse) => [
                'id'       => $warehouse->id,
                'name'     => $warehouse->name,
                'code'     => $warehouse->code,
                'city'     => $warehouse->city,
                'state'    => $warehouse->state,
                'country'  => $warehouse->country,
                'active'   => $warehouse->active,
            ]);

        return Inertia::render('Warehouses/Index', [
            'warehouses' => $warehouses,
            'filters' => $request->only('search'),
        ]);
    }

    /**
     * Show Create Page
     */
    public function create()
    {
        return Inertia::render('Warehouses/Form');
    }

    /**
     * Store new warehouse
     */
    public function store(StoreWarehouseRequest $request)
    {
        Warehouse::create($request->validated());

        return redirect()->route('warehouses.index')
            ->with('success', 'Warehouse created successfully.');
    }

    /**
     * Show Edit Page
     */
    public function edit(Warehouse $warehouse)
    {
        return Inertia::render('Warehouses/Form', [
            'warehouse' => $warehouse
        ]);
    }

    /**
     * Update warehouse details
     */
    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse)
    {
        $warehouse->update($request->validated());

        return redirect()->route('warehouses.index')
            ->with('success', 'Warehouse updated successfully.');
    }

    /**
     * Soft delete warehouse
     */
    public function destroy(Warehouse $warehouse)
    {
        $warehouse->delete();

        return redirect()->route('warehouses.index')
            ->with('success', 'Warehouse deleted successfully.');
    }
}
