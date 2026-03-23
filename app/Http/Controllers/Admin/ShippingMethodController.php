<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Shipping\StoreShippingMethodRequest;
use App\Http\Requests\Admin\Shipping\UpdateShippingMethodRequest;
use App\Models\ShippingMethod;
use App\Services\ShippingManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShippingMethodController extends Controller
{
    public function __construct(
        protected ShippingManagementService $shippingManagementService,
    ) {}

    public function index(Request $request): Response
    {
        $methods = $this->shippingManagementService->listMethods($request->only(['search', 'status', 'type']));

        return Inertia::render('Admin/ShippingMethods/Index', [
            'filters' => $request->only(['search', 'status', 'type']),
            'methods' => $methods->through(fn (ShippingMethod $method) => $this->shippingManagementService->toMethodListPayload($method)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/ShippingMethods/Form', [
            'mode' => 'create',
            'shippingMethod' => null,
            ...$this->shippingManagementService->formOptionsForMethods(),
        ]);
    }

    public function store(StoreShippingMethodRequest $request): RedirectResponse
    {
        $method = $this->shippingManagementService->createMethod($request->validated());

        return redirect()
            ->route('admin.shipping-methods.edit', $method)
            ->with('success', 'Shipping method created successfully.');
    }

    public function edit(ShippingMethod $shippingMethod): Response
    {
        return Inertia::render('Admin/ShippingMethods/Form', [
            'mode' => 'edit',
            'shippingMethod' => $this->shippingManagementService->toMethodFormPayload($shippingMethod),
            ...$this->shippingManagementService->formOptionsForMethods(),
        ]);
    }

    public function update(UpdateShippingMethodRequest $request, ShippingMethod $shippingMethod): RedirectResponse
    {
        $this->shippingManagementService->updateMethod($shippingMethod, $request->validated());

        return back()->with('success', 'Shipping method updated successfully.');
    }

    public function toggleStatus(ShippingMethod $shippingMethod): RedirectResponse
    {
        $shippingMethod->update(['is_active' => !$shippingMethod->is_active]);

        return back()->with('success', 'Shipping method status updated.');
    }
}
